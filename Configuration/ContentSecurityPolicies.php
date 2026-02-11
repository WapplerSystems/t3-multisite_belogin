<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Map;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$currentHost = GeneralUtility::getIndpEnv('HTTP_HOST');
$mutations = [];

if ($currentHost !== '') {
    $requestHost = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
    $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    $sites = $siteFinder->getAllSites();

    foreach ($sites as $site) {
        $siteBase = $site->getBase()->__toString();
        if (str_starts_with($siteBase, $requestHost)) {
            $mutations[] = new Mutation(
                MutationMode::Extend,
                Directive::ConnectSrc,
                new UriValue($requestHost),
            );
            break;
        }
    }
}

$collection = new MutationCollection(...$mutations);

return Map::fromEntries([
    // Provide declarations for the backend
    Scope::backend(),
    $collection
    ]
);
