<?php

use TYPO3\CMS\Backend\Controller\AboutController;

/**
 */
return [
    'multisitebelogin' => [
        'labels' => 'LLL:EXT:core/Resources/Private/Language/locallang_mod_help.xlf',
        'iconIdentifier' => 'modulegroup-help',
        'appearance' => [
            'renderInModuleMenu' => false,
        ],
    ],
    'status' => [
        'parent' => 'multisitebelogin',
        'position' => ['before' => '*'],
        'access' => 'user',
        'path' => '/module/multisitenelogin/status',
        'iconIdentifier' => 'module-about',
        'labels' => 'LLL:EXT:backend/Resources/Private/Language/Modules/about.xlf',
        'aliases' => ['multisitebelogin_Status'],
        'routes' => [
            '_default' => [
                'target' => AboutController::class . '::handleRequest',
            ],
        ],
    ],
];
