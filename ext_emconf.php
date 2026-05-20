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
    'version' => '13.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
        ],
    ],
];

