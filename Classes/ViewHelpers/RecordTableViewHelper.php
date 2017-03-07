<?php
namespace Netlogix\Nxgooglelocations\ViewHelpers;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

class RecordTableViewHelper extends AbstractViewHelper implements SingletonInterface
{
    /**
     * @param string $recordTableName
     * @param int $storagePageId
     * @param bool $showEditIcons
     * @param int $limit
     * @param array <string> $fieldsList
     * @return string
     */
    public function render($recordTableName, $storagePageId, $showEditIcons = true, $limit = 2500, $fieldsList = [])
    {
        $pageLayoutView = new \TYPO3\CMS\Backend\View\PageLayoutView();
        $pageLayoutView->pidSelect = sprintf('pid = %d', $storagePageId);
        $pageLayoutView->externalTables = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables'];
        if (!array_key_exists($recordTableName, $pageLayoutView->externalTables)) {
            $pageLayoutView->externalTables[$recordTableName] = [[
                'fList' => join(',', array_keys($GLOBALS['TCA'][$recordTableName]['columns'])),
                'icon' => true,
            ]];
        }
        if ($fieldsList) {
            $pageLayoutView->externalTables[$recordTableName][0]['fList'] = join(',', $fieldsList);
        }
        $pageLayoutView->doEdit = $showEditIcons;
        $pageLayoutView->iLimit = $limit;
        $result = str_replace('data-identifier="status-status-edit-read-only"', ' style="display: none;"', $pageLayoutView->getExternalTables($storagePageId, $recordTableName));
        return trim($result) ? $result : $this->renderChildren();
    }
}