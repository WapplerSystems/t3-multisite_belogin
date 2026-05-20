<?php

$requestMiddlewares = [
    'wapplersystems/multisite-token-authenticator' => [
        'target' => \WapplerSystems\MultisiteBelogin\Middleware\TokenLoginAuthenticator::class,
        'before' => ['typo3/cms-backend/backend-routing'],
        'after' => [],
    ],
];

return [
    'backend' => $requestMiddlewares,
];
