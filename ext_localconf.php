<?php
defined('TYPO3') || die();

call_user_func(
    function ($extKey) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['Newt']['Implementation'][] = \Infonique\Newt4News\Newt\NewsEndpoint::class;
    },
    'newt4news'
);
