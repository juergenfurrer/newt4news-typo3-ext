<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4News',
    'description' => 'Extension with the Newt-Provider for News',
    'category' => 'be',
    'author' => 'Juergen Furrer',
    'author_email' => 'juergen@infonique.ch',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '2.0.0-',
            'news' => '8.0.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
