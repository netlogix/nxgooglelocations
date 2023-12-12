<?php

declare(strict_types=1);

use Netlogix\Nxgooglelocations\Command\BatchCommand;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = BatchCommand::class;

    GeneralUtility::makeInstance(IconRegistry::class)->registerIcon(
        'ext-nxgooglelocations-batch-type-default',
        SvgIconProvider::class,
        [
            'source' => 'EXT:nxgooglelocations/Resources/Public/Icons/tx_nxgooglelocations_domain_model_batch.svg',
        ]
    );
});
