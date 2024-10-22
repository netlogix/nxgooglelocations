<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Controller;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use Netlogix\Nxgooglelocations\Domain\Repository\BatchRepository;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SJBR\StaticInfoTables\Domain\Model\Country;
use SJBR\StaticInfoTables\Domain\Repository\CountryRepository;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
abstract class ModuleController extends ActionController
{
    protected array $pageRecord = [];

    protected ?ModuleTemplate $moduleTemplate = null;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory,
        private readonly CountryRepository $countryRepository,
        private readonly BatchRepository $batchRepository,
    ) {
    }

    protected function initializeAction(): void
    {
        parent::initializeAction();

        $id = (int) ($this->request->getQueryParams()['id'] ?? 0);

        $this->pageRecord = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()
                ->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $docHeaderComponent = $this->moduleTemplate->getDocHeaderComponent();
        $docHeaderComponent->setMetaInformation($this->pageRecord);

        $buttonBar = $docHeaderComponent->getButtonBar();

        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref($this->request->getUri())
            ->setTitle(
                $this->getLanguageService()
                    ->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload')
            )
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));

        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    protected function getModuleTemplateResponse(): ResponseInterface
    {
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function indexAction(int $id): ResponseInterface
    {
        if (!$this->settings['enabled']) {
            return $this->forwardToErrorWithCannedMessage('disabled');
        }

        if (!$this->settings['apiKey']) {
            return $this->forwardToErrorWithCannedMessage('missing-api-key');
        }

        $allowedCountryCodes = GeneralUtility::trimExplode(',', $this->settings['allowedCountries'], true);
        if ($allowedCountryCodes === []) {
            return $this->forwardToErrorWithCannedMessage('missing-countries');
        }

        if (strtoupper(implode('', $allowedCountryCodes)) === 'ALL') {
            $this->view->assign('allowedCountries', $this->countryRepository->findAll());
        } else {
            $this->view->assign(
                'allowedCountries',
                $this->countryRepository->findAllowedByIsoCodeA3(implode(',', $allowedCountryCodes))
            );
        }

        $this->view->assign('id', $id);
        $this->view->assign('localLangExtensionName', $this->getExtensionNameForLocalLang());
        $this->view->assign('batches', [
            'tableName' => $this->getTableNameForBatchRecords(),
            'fieldList' => ['file_name', 'state', 'amount', 'position', 'tstamp'],
        ]);
        $this->view->assign('locations', [
            'tableName' => $this->getTableNameForLocationRecords(),
        ]);

        $this->view->assign('enableImport', $this->isImportEnabled());
        $this->view->assign('enableExport', $this->isExportEnabled());

        return $this->getModuleTemplateResponse();
    }

    public function importAction(
        int $id,
        bool $deleteUnused,
        bool $cancelPrevious,
        Country $country = null
    ): ResponseInterface {
        $file = $this->request->getUploadedFiles()['excelFile'] ?? null;
        if ($file === null) {
            throw new RuntimeException('Uploading file failed.', 1702385736);
        }

        $batch = $this->mapRequestToBatch($id, $file, $deleteUnused, $cancelPrevious, $country);

        try {
            $batch->validate();
        } catch (Exception $exception) {
            $this->addFlashMessage(
                $exception->getMessage(),
                '',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->redirect('index');
        }

        $extensionName = $this->getExtensionNameForLocalLang();

        if ($cancelPrevious) {
            /** @var Batch $previousBatch */
            foreach ($this->batchRepository->findOpenInFolder($id) as $previousBatch) {
                $previousBatch->cancel();
                $this->batchRepository->update($previousBatch);
                $this->addFlashMessage(
                    LocalizationUtility::translate(
                        'module.flash-messages.job-canceled.content',
                        $extensionName,
                        [$previousBatch->getFileName()]
                    )
                );
            }
        }

        $this->batchRepository->add($batch);
        $this->addFlashMessage(
            LocalizationUtility::translate('module.flash-messages.new-job-scheduled.content', $extensionName)
        );

        return $this->redirect('index');
    }

    public function exportAction(int $id): ResponseInterface
    {
        return $this->htmlResponse();
    }

    protected function errorAction(): ResponseInterface
    {
        return $this->getModuleTemplateResponse()
            ->withStatus(400);
    }

    protected function forwardToErrorWithCannedMessage(string $reason): ResponseInterface
    {
        $extensionName = $this->getExtensionNameForLocalLang();
        $this->addFlashMessage(
            LocalizationUtility::translate(sprintf('module.flash-messages.%s.content', $reason), $extensionName),
            LocalizationUtility::translate(sprintf('module.flash-messages.%s.title', $reason), $extensionName),
            ContextualFeedbackSeverity::ERROR
        );

        return $this->redirect('error');
    }

    protected function getExtensionNameForLocalLang(): string
    {
        return 'nxgooglelocations';
    }

    protected function getTableNameForBatchRecords(): string
    {
        return 'tx_nxgooglelocations_domain_model_batch';
    }

    abstract protected function getTableNameForLocationRecords(): string;

    abstract protected function mapRequestToBatch(
        $id,
        UploadedFile $file,
        $deleteUnused,
        $cancelPrevious,
        Country $country = null
    ): Batch;

    protected function isImportEnabled(): bool
    {
        return empty($this->settings['disableImport']);
    }

    protected function isExportEnabled(): bool
    {
        return empty($this->settings['disableExport']);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
