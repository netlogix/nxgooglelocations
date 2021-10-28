<?php

namespace Netlogix\Nxgooglelocations\ViewHelpers\Be;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class TableListViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Be\TableListViewHelper
{
    public function render(): string
    {
        $tableName = $this->arguments['tableName'];
        $preventPointer = $tableName !== GeneralUtility::_GP('table');

        try {
            if ($preventPointer) {
                $backup = [
                    'get' => $_GET,
                    'post' => $_POST
                ];
                $_GET['pointer'] = 0;
                $_POST['pointer'] = 0;
            }
            $result = parent::render();
            return is_int(strpos($result, '<table')) ? $result : $this->renderChildren();

        } finally {
            $_GET = $backup['get'];
            $_POST = $backup['post'];
        }
    }
}
