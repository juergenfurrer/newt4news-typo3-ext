<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4News',
    'description' => 'Extension with the Newt-Provider for News',
    'category' => 'be',
    'author' => 'Juergen Furrer',
    'author_email' => 'juergen@infonique.ch',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '1.7.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '1.9.2-',
            'news' => '8.0.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
