<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Backend\View\BackendLayout;

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

use TYPO3\CMS\Core\SingletonInterface;

class RecordRememberer implements SingletonInterface
{
    /**
     * @var int[]
     */
    protected $rememberedUids = [];

    public function rememberRecordUid(int $uid): void
    {
        $this->rememberedUids[$uid] = $uid;
    }

    public function isRemembered(int $uid): bool
    {
        return isset($this->rememberedUids[$uid]);
    }
}
