<?php
namespace TYPO3\CMS\Core\Resource\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * File processing service
 */
class FileProcessingService
{
    /**
     * @var Resource\ResourceStorage
     */
    protected $storage;

    /**
     * @var Resource\Driver\DriverInterface
     */
    protected $driver;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @deprecated since TYPO3 v10.2, will be removed in TYPO3 v11. Use the PSR-14 event instead.
     */
    const SIGNAL_PreFileProcess = 'preFileProcess';

    /**
     * @deprecated since TYPO3 v10.2, will be removed in TYPO3 v11. Use the PSR-14 event instead.
     */
    const SIGNAL_PostFileProcess = 'postFileProcess';

    /**
     * Creates this object.
     *
     * @param Resource\ResourceStorage $storage
     * @param Resource\Driver\DriverInterface $driver
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(Resource\ResourceStorage $storage, Resource\Driver\DriverInterface $driver, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->storage = $storage;
        $this->driver = $driver;
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
    }

    /**
     * Processes a file
     *
     * @param Resource\FileInterface $fileObject The file object
     * @param Resource\ResourceStorage $targetStorage The storage to store the processed file in
     * @param string $taskType
     * @param array $configuration
     *
     * @return Resource\ProcessedFile
     * @throws \InvalidArgumentException
     */
    public function processFile(Resource\FileInterface $fileObject, Resource\ResourceStorage $targetStorage, $taskType, $configuration)
    {
        // Enforce default configuration for preview processing here,
        // to be sure we find already processed files below,
        // which we wouldn't if we would change the configuration later, as configuration is part of the lookup.
        if ($taskType === Resource\ProcessedFile::CONTEXT_IMAGEPREVIEW) {
            $configuration = Resource\Processing\LocalPreviewHelper::preProcessConfiguration($configuration);
        }
        // Ensure that the processing configuration which is part of the hash sum is properly cast, so
        // unnecessary duplicate images are not produced, see #80942
        foreach ($configuration as &$value) {
            if (MathUtility::canBeInterpretedAsInteger($value)) {
                $value = (int)$value;
            }
        }

        /** @var Resource\ProcessedFileRepository $processedFileRepository */
        $processedFileRepository = GeneralUtility::makeInstance(Resource\ProcessedFileRepository::class);

        $processedFile = $processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration($fileObject, $taskType, $configuration);

        // set the storage of the processed file
        // Pre-process the file
        /** @var Resource\Event\BeforeFileProcessingEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new Resource\Event\BeforeFileProcessingEvent($this->driver, $processedFile, $fileObject, $taskType, $configuration)
        );
        $processedFile = $event->getProcessedFile();

        // Only handle the file if it is not processed yet
        // (maybe modified or already processed by a signal)
        // or (in case of preview images) already in the DB/in the processing folder
        if (!$processedFile->isProcessed()) {
            $this->process($processedFile, $targetStorage);
        }

        // Post-process (enrich) the file
        /** @var Resource\Event\AfterFileProcessingEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new Resource\Event\AfterFileProcessingEvent($this->driver, $processedFile, $fileObject, $taskType, $configuration)
        );

        return $event->getProcessedFile();
    }

    /**
     * Processes the file
     *
     * @param Resource\ProcessedFile $processedFile
     * @param Resource\ResourceStorage $targetStorage The storage to put the processed file into
     */
    protected function process(Resource\ProcessedFile $processedFile, Resource\ResourceStorage $targetStorage)
    {
        // We only have to trigger the file processing if the file either is new, does not exist or the
        // original file has changed since the last processing run (the last case has to trigger a reprocessing
        // even if the original file was used until now)
        if ($processedFile->isNew() || (!$processedFile->usesOriginalFile() && !$processedFile->exists()) ||
            $processedFile->isOutdated()) {
            $task = $processedFile->getTask();
            $processor = $this->getProcessorByTask($task);
            $processor->processTask($task);

            if ($task->isExecuted() && $task->isSuccessful() && $processedFile->isProcessed()) {
                /** @var Resource\ProcessedFileRepository $processedFileRepository */
                $processedFileRepository = GeneralUtility::makeInstance(Resource\ProcessedFileRepository::class);
                $processedFileRepository->add($processedFile);
            }
        }
    }

    /**
     * @param Resource\Processing\TaskInterface $task
     * @return Resource\Processing\ProcessorInterface
     */
    protected function getProcessorByTask(Resource\Processing\TaskInterface $task): Resource\Processing\ProcessorInterface
    {
        $processorRegistry = GeneralUtility::makeInstance(Resource\Processing\ProcessorRegistry::class);

        return $processorRegistry->getProcessorByTask($task);
    }
}
