<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4News',
    'description' => 'Extension with the Newt-Provider for News',
    'category' => 'be',
    'author' => 'Juergen Furrer',
    'author_email' => 'juergen@infonique.ch',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.4.3',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '1.7.1-',
            'news' => '8.0.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
