<?php

namespace Netlogix\Nxgooglelocations\ViewHelpers\Be;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;

/**
 * This view helper is mainly a carbon copy of the TYPO3\CMS\Fluid\ViewHelpers\Be\TableListViewHelper
 * which is declared final since TYPO3 v12. This view helper is needed to be able to use the table list
 * view helper in a backend module and to disable the download button.
 */
final class TableListViewHelper extends AbstractBackendViewHelper
{
    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    protected ConfigurationManagerInterface $configurationManager;

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('tableName', 'string', 'name of the database table', true);
        $this->registerArgument(
            'fieldList',
            'array',
            'list of fields to be displayed. If empty, only the title column (configured in $TCA[$tableName][\'ctrl\'][\'title\']) is shown',
            false,
            []
        );
        $this->registerArgument(
            'storagePid',
            'int',
            'by default, records are fetched from the storage PID configured in persistence.storagePid. With this argument, the storage PID can be overwritten'
        );
        $this->registerArgument(
            'levels',
            'int',
            'corresponds to the level selector of the TYPO3 list module. By default only records from the current storagePid are fetched',
            false,
            0
        );
        $this->registerArgument(
            'filter',
            'string',
            'corresponds to the "Search String" textbox of the TYPO3 list module. If not empty, only records matching the string will be fetched',
            false,
            ''
        );
        $this->registerArgument(
            'recordsPerPage',
            'int',
            'amount of records to be displayed at once. Defaults to $TCA[$tableName][\'interface\'][\'maxSingleDBListItems\'] or (if that\'s not set) to 100',
            false,
            0
        );
        $this->registerArgument('sortField', 'string', 'table field to sort the results by', false, '');
        $this->registerArgument(
            'sortDescending',
            'bool',
            'if TRUE records will be sorted in descending order',
            false,
            false
        );
        $this->registerArgument(
            'readOnly',
            'bool',
            "if TRUE, the edit icons won't be shown. Otherwise edit icons will be shown, if the current BE user has edit rights for the specified table!",
            false,
            false
        );
        $this->registerArgument('enableClickMenu', 'bool', 'enables context menu', false, true);
        $this->registerArgument('enableControlPanels', 'bool', 'enables control panels', false, false);
        $this->registerArgument(
            'clickTitleMode',
            'string',
            'one of "edit", "show" (only pages, tt_content), "info',
            false,
            ''
        );
    }

    /**
     * Renders a record list as known from the TYPO3 list module
     * Note: This feature is experimental!
     *
     * @see \TYPO3\CMS\Backend\RecordList\DatabaseRecordList
     */
    protected function render(): string
    {
        $tableName = $this->arguments['tableName'];
        $fieldList = $this->arguments['fieldList'];
        $storagePid = $this->arguments['storagePid'];
        $levels = $this->arguments['levels'];
        $filter = $this->arguments['filter'];
        $recordsPerPage = $this->arguments['recordsPerPage'];
        $sortField = $this->arguments['sortField'];
        $sortDescending = $this->arguments['sortDescending'];
        $readOnly = $this->arguments['readOnly'];
        $enableClickMenu = $this->arguments['enableClickMenu'];
        $clickTitleMode = $this->arguments['clickTitleMode'];
        $enableControlPanels = $this->arguments['enableControlPanels'];

        $this->getLanguageService();
        $backendUser = $this->getBackendUser();
        /** @var RenderingContext $renderingContext */
        $renderingContext = $this->renderingContext;
        $request = $renderingContext->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            // All views in backend have at least ServerRequestInterface, no matter if created by
            // old StandaloneView via BackendViewFactory. Should be fine to assume having a request
            // here, the early return is just sanitation.
            return '';
        }

        // Added to fix the issue with the table pointer
        $table = $request->getParsedBody()['table'] ?? $request->getQueryParams()['table'] ?? '';
        $preventPointer = $tableName !== $table;

        $this->getPageRenderer()->loadJavaScriptModule('@typo3/backend/recordlist.js');
        // Removed to disable the download button
        // $this->getPageRenderer()->loadJavaScriptModule('@typo3/backend/record-download-button.js');
        $this->getPageRenderer()->loadJavaScriptModule('@typo3/backend/action-dispatcher.js');
        if ($enableControlPanels === true) {
            $this->getPageRenderer()->loadJavaScriptModule('@typo3/backend/multi-record-selection.js');
            $this->getPageRenderer()->loadJavaScriptModule('@typo3/backend/context-menu.js');
        }

        $pageId = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);

        // Added to fix the issue with the table pointer
        $pointer = $preventPointer
            ? 0
            : (int)($request->getParsedBody()['pointer'] ?? $request->getQueryParams()['pointer'] ?? 0);
        $pageInfo = BackendUtility::readPageAccess(
            $pageId,
            $backendUser->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];
        $existingModuleData = $backendUser->getModuleData('web_list');
        $moduleData = new ModuleData('web_list', is_array($existingModuleData) ? $existingModuleData : []);

        $dbList = GeneralUtility::makeInstance(DatabaseRecordList::class);
        // Added to disable the download button
        $dbList->displayRecordDownload = false;
        $dbList->setRequest($request);
        $dbList->setModuleData($moduleData);

        $dbList->pageRow = $pageInfo;
        if ($readOnly) {
            $dbList->setIsEditable(false);
        } else {
            $dbList->calcPerms = new Permission($backendUser->calcPerms($pageInfo));
        }

        $dbList->disableSingleTableView = true;
        $dbList->clickTitleMode = $clickTitleMode;
        $dbList->clickMenuEnabled = $enableClickMenu;
        if ($storagePid === null) {
            $frameworkConfiguration = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
            );
            $storagePid = $frameworkConfiguration['persistence']['storagePid'];
        }

        $dbList->start($storagePid, $tableName, $pointer, $filter, $levels, $recordsPerPage);
        // Column selector is disabled since fields are defined by the "fieldList" argument
        $dbList->displayColumnSelector = false;
        $dbList->setFields = [$tableName => $fieldList];
        $dbList->noControlPanels = !$enableControlPanels;
        $dbList->sortField = $sortField;
        $dbList->sortRev = $sortDescending;
        return $dbList->generateList();
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
