<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Controller;

use Netlogix\Nxgooglelocations\Service\FrontendSettingsForBackendProvider;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

class AbstractBackendModuleController extends ActionController
{
    public $controllerContext;

    /**
     * @var array
     */
    protected $pageRecord = [];

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory
    ) {
    }

    /**
     * Object initialization
     */
    protected function initializeAction()
    {
        $id = (int) GeneralUtility::_GP('id');
        $this->request->setArgument('id', $id);
        $backendUser = $this->getBackendUser();
        $perms_clause = $backendUser->getPagePermsClause(1);
        $this->pageRecord = BackendUtility::readPageAccess($id, $perms_clause) ?: [];
        $this->settings = FrontendSettingsForBackendProvider::getConfigurationForPageId($id);
    }

    public function initializeView(ViewInterface $view): void
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->registerDocheaderButtons();
        $moduleTemplate
            ->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
        $moduleTemplate
            ->getDocHeaderComponent()
            ->setMetaInformation($this->pageRecord);
    }

    protected function registerDocheaderButtons()
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        /** @var ButtonBar $buttonBar */
        $buttonBar = $moduleTemplate
            ->getDocHeaderComponent()
            ->getButtonBar();

        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref(GeneralUtility::getIndpEnv('REQUEST_URI'))
            ->setTitle(
                $this->getLanguageService()
                    ->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload')
            )
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    /**
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
