<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use WapplerSystems\MultisiteBelogin\Authentication\TokenAuthenticationService;
use WapplerSystems\MultisiteBelogin\Backend\ToolbarItems\StatusToolbarItem;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Middleware\BackendUserAuthenticator::class] = [
    'className' => WapplerSystems\MultisiteBelogin\Middleware\BackendUserAuthenticator::class
];


$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = true;

ExtensionManagementUtility::addService(
    'multisite_belogin',
    'auth',
    TokenAuthenticationService::class,
    [
        'title' => 'User authentication',
        'description' => 'Authentication by token.',
        'subtype' => 'getUserBE,getUserFE,authUserBE,authUserFE',
        'available' => true,
        'priority' => 90,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => TokenAuthenticationService::class,
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][1688982809] = StatusToolbarItem::class;


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_pre_processing'][] = \WapplerSystems\MultisiteBelogin\Hooks\LogoutHook::class . '->execute';
