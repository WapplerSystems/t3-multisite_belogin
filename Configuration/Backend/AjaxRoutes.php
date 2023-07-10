<?php

use WapplerSystems\MultisiteBelogin\Controller\BackendLoginController;
use WapplerSystems\MultisiteBelogin\Controller\SitesController;
use WapplerSystems\MultisiteBelogin\Controller\TokenController;

return [

    'multisitebelogin_sites' => [
        'path' => '/msbl/sites',
        'target' => SitesController::class . '::listAction',
    ],
    'multisitebelogin_status' => [
        'path' => '/msbl/status',
        'target' => BackendLoginController::class . '::statusAction',
        'access' => 'public',
    ],
    'multisitebelogin_token_generate' => [
        'path' => '/msbl/token/generate',
        'target' => TokenController::class . '::generateAction',
        'access' => 'public',
    ],
    'multisitebelogin_login' => [
        'path' => '/msbl/login',
        'target' => BackendLoginController::class . '::loginAction',
        'access' => 'public',
    ],

];
