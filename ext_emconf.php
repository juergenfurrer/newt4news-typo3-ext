<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Newt4News',
    'description' => 'Extension with the Newt-Provider for News',
    'category' => 'be',
    'author' => 'infonique, furrer',
    'author_email' => 'info@infonique.ch',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '2.2.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'newt' => '2.2.0-2.2.99',
            'news' => '8.0.0-10.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
