<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use WapplerSystems\MultisiteBelogin\Authentication\TokenAuthenticationService;

$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = true;

ExtensionManagementUtility::addService(
    'multisite_belogin',
    'auth',
    TokenAuthenticationService::class,
    [
        'title' => 'User authentication',
        'description' => 'Authentication by token.',
        'subtype' => 'getUserBE,authUserBE',
        'available' => true,
        'priority' => 90,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => TokenAuthenticationService::class,
    ]
);
