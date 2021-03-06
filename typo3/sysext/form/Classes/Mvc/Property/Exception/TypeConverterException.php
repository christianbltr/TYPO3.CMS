<?php
namespace TYPO3\CMS\Form\Mvc\Property\Exception;

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

use TYPO3\CMS\Extbase\Error\Error;

/**
 * A "Type Converter" Exception
 */
final class TypeConverterException extends \TYPO3\CMS\Extbase\Property\Exception\TypeConverterException
{
    /**
     * @var Error
     */
    protected $error;

    public static function fromError(Error $error): TypeConverterException
    {
        // [phpstan] Unsafe usage of new static()
        // todo: Either mark this class or its constructor final or use new self instead.
        $exception = new static($error->getMessage(), $error->getCode());
        $exception->error = $error;

        return $exception;
    }

    public function getError(): Error
    {
        if (empty($this->error)) {
            return new Error($this->getMessage(), $this->getCode(), [$this->getPrevious()]);
        }

        return $this->error;
    }
}
