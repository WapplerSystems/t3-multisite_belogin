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
$siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
$sites = $siteFinder->getAllSites();

$mutations = [];

foreach ($sites as $site) {
    $host = parse_url($site->getBase()->__toString(), PHP_URL_HOST);
    if ($host && $host !== $currentHost) {
        $mutations[] = new Mutation(
            MutationMode::Extend,
            Directive::ConnectSrc,
            new UriValue($site->getBase()->__toString()),
        );
    }
}
$collection = new MutationCollection(...$mutations);

return Map::fromEntries([
    // Provide declarations for the backend
    Scope::backend(),
    $collection
    ]
);
