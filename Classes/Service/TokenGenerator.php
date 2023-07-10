<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Service;

use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TokenGenerator
{

    public function generate(): string
    {
        return GeneralUtility::makeInstance(Random::class)->generateRandomHexString(40);
    }

}
