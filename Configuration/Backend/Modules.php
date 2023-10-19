<?php

use TYPO3\CMS\Backend\Controller\AboutController;

/**
 */
return [
    'multisitebelogin' => [
        'labels' => 'LLL:EXT:multisite_belogin/Resources/Private/Language/backend.xlf',
        'iconIdentifier' => 'multisite-belogin-module',
        'appearance' => [
            'renderInModuleMenu' => false,
        ],
    ],
    'multisitebelogin-status' => [
        'parent' => 'multisitebelogin',
        'position' => ['before' => '*'],
        'access' => 'user',
        'path' => '/module/multisitenelogin/status',
        'iconIdentifier' => 'multisite-belogin-module-status',
        'labels' => 'LLL:EXT:multisite_belogin/Resources/Private/Language/status.xlf',
        'aliases' => ['multisitebelogin_Status'],
        'routes' => [
            '_default' => [
                'target' => AboutController::class . '::handleRequest',
            ],
        ],
    ],
];
