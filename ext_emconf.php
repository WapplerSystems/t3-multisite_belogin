<?php

$EM_CONF['multisite_belogin'] = [
    'title' => 'Multisite Backend Login',
    'description' => 'Cross site/domain backend login for TYPO3. It allows backend users to work in the frontend across domains.',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3@wappler.systems',
    'category' => 'be',
    'author_company' => 'WapplerSystems',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '12.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.8-12.4.99',
        ],
    ],
];

