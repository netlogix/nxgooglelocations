<?php

declare(strict_types=1);

defined('TYPO3') or die();

call_user_func(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \Netlogix\Nxgooglelocations\Command\BatchCommand::class;

    if (TYPO3_MODE === 'BE') {
        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Imaging\IconRegistry::class
        );
        $iconRegistry->registerIcon(
            'ext-nxgooglelocations-batch-type-default',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            [
                'source' => 'EXT:nxgooglelocations/Resources/Public/Icons/tx_nxgooglelocations_domain_model_batch.svg',
            ]
        );
    }
});
