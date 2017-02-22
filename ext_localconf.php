<?php
defined('TYPO3_MODE') or die();

call_user_func(function () {

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \Netlogix\Nxgooglelocations\Command\BatchCommandController::class;

});