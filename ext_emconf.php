<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 MongoDB cache backend',
    'description' => 'With this cache backend you can store and use data in MongoDB in TYPO3 via the cache manager',
    'category' => 'plugin',
    'author' => 'Guido Schmechel',
    'author_email' => 'info@ayacoo.de',
    'state' => 'stable',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '11.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.4.0-11.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
