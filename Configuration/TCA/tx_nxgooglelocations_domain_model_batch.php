<?php
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3_MODE') or die();

$_EXTKEY = 'nxgooglelocations';
$LLL = 'LLL:EXT:nxgooglelocations/Resources/Private/Language/locallang_db.xlf:tx_nxgooglelocations_domain_model_batch';

$value = [
    'ctrl' => [
        'title' => $LLL,
        'label' => 'file_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
        'type' => 'type',
        'delete' => 'deleted',
        'default_sortby' => 'tstamp DESC, uid DESC',
        'enablecolumns' => [
        ],
        'searchFields' => 'file_name',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'ext-nxgooglelocations-batch-type-default',
        ],
    ],
    'interface' => [
        'showRecordFieldList' => '',
    ],
    'types' => [
        '0' => [
            'showitem' => '
                type,
                tstamp,
                delete_unused,
                state,
                amount,
                position,
                geocoding_requests,
                api_key,
                storage_page_id,
                backend_user_id,
                file_name,
                file_hash
            '
        ],
    ],
    'palettes' => [],
    'columns' => [
        'type' => [
            'config' => [
                'type' => 'select',
                'size' => 1,
                'maxitems' => 1,
                'renderType' => 'selectSingle',
                'items' => [],
                'required' => true,
            ]
        ],
        'tstamp' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'datetime',
            ],
        ],
        'state' => [
            'config' => [
                'type' => 'select',
                'items' => [
                    [$LLL . '.state.I.new', 'new'],
                    [$LLL . '.state.I.validating', 'validating'],
                    [$LLL . '.state.I.running', 'running'],
                    [$LLL . '.state.I.persisting', 'persisting'],
                    [$LLL . '.state.I.closed', 'closed'],
                ],
                'size' => 1,
                'maxitems' => 1,
                'renderType' => 'selectSingle',
                'required' => true,
            ]
        ],
        'delete_unused' => [
            'config' => [
                'type' => 'check',
            ],
        ],
        'amount' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'position' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'geocoding_requests' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'api_key' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'storage_page_id' => [
            'config' => [
                'type' => 'select',
                'size' => 1,
                'maxitems' => 1,
                'renderType' => 'selectSingle',
                'foreign_table' => 'pages',
            ],
        ],
        'backend_user_id' => [
            'config' => [
                'type' => 'select',
                'size' => 1,
                'maxitems' => 1,
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_users',
            ],
        ],
        'file_name' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'file_content' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'file_hash' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
    ],
];

foreach ($value['columns'] as $columName => $columnConfig) {
    $value['columns'][$columName]['label'] = $LLL . '.' . $columName;
    $value['columns'][$columName]['exclude'] = false;
    $value['columns'][$columName]['config']['readOnly'] = true;
}
$value['columns']['api_key']['exclude'] = true;

return $value;
