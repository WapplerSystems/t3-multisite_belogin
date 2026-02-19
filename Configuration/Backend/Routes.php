<?php


use WapplerSystems\MultisiteBelogin\Controller\LoginController;

return [
    'multisitebelogin_redirect' => [
        'path' => '/msbl/redirectToFrontend',
        'target' => LoginController::class . '::redirectToFrontendAction',
    ],
    'multisitebelogin_tokenauth' => [
        'path' => '/msbl/tokenauth',
        'access' => 'public',
    ],

];
