<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Controller;

use Netlogix\Nxgooglelocations\Service\FrontendSettingsForBackendProvider;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Lang\LanguageService;

class AbstractBackendModuleController extends ActionController
{
    /**
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * @var BackendTemplateView
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var array
     */
    protected $pageRecord = [];

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

    public function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        $this->registerDocheaderButtons();
        $this->view->getModuleTemplate()
            ->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
        $this->view->getModuleTemplate()
            ->getDocHeaderComponent()
            ->setMetaInformation($this->pageRecord);
    }

    protected function registerDocheaderButtons()
    {
        /** @var ButtonBar $buttonBar */
        $buttonBar = $this->view->getModuleTemplate()
            ->getDocHeaderComponent()
            ->getButtonBar();

        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref(GeneralUtility::getIndpEnv('REQUEST_URI'))
            ->setTitle(
                $this->getLanguageService()
                    ->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload')
            )
            ->setIcon($this->view->getModuleTemplate()->getIconFactory()->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    /**
     * @return LanguageService
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
