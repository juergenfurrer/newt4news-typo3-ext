<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4News',
    'description' => 'Extension with the Newt-Provider for News',
    'category' => 'be',
    'author' => 'Swisscode',
    'author_email' => 'info@swisscode.sk',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '3.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
            'newt' => '3.0.0-',
            'news' => '11.0.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
