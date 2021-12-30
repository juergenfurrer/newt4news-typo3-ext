<?php
defined('TYPO3') || die();

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['Newt']['Implementation'][] = \Infonique\Newt4News\Newt\NewsEndpoint::class;
})();
