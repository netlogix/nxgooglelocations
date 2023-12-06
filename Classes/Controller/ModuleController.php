<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Controller;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use Netlogix\Nxgooglelocations\Domain\Repository\BatchRepository;
use SJBR\StaticInfoTables\Domain\Model\Country;
use SJBR\StaticInfoTables\Domain\Repository\CountryRepository;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

abstract class ModuleController extends AbstractBackendModuleController
{
    /**
     * @var CountryRepository
     */
    protected $countryRepository;

    /**
     * @var BatchRepository
     */
    protected $batchRepository;

    public function injectCountryRepository(CountryRepository $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    public function injectBatchRepository(BatchRepository $batchRepository)
    {
        $this->batchRepository = $batchRepository;
    }

    /**
     * @param int $id
     */
    public function indexAction($id)
    {
        if (!$this->settings['enabled']) {
            $this->forwardToErrorWithCannedMessage('disabled');
        }
        if (!$this->settings['apiKey']) {
            $this->forwardToErrorWithCannedMessage('missing-api-key');
        }

        $allowedCountryCodes = GeneralUtility::trimExplode(',', $this->settings['allowedCountries'], true);
        if (!$allowedCountryCodes) {
            $this->forwardToErrorWithCannedMessage('missing-countries');
        }

        if (strtoupper(join('', $allowedCountryCodes)) === 'ALL') {
            $this->view->assign('allowedCountries', $this->countryRepository->findAll());
        } else {
            $this->view->assign(
                'allowedCountries',
                $this->countryRepository->findAllowedByIsoCodeA3(join(',', $allowedCountryCodes))
            );
        }

        $this->view->assign('id', $id);
        $this->view->assign('localLangExtensionName', $this->getExtensionNameForLocalLang());
        $this->view->assign('batches', [
            'tableName' => $this->getTabeNameForBatchRecords(),
            'fieldList' => ['file_name', 'state', 'amount', 'position', 'tstamp'],
        ]);
        $this->view->assign('locations', [
            'tableName' => $this->getTableNameForLocationRecords(),
        ]);

        $this->view->assign('enableImport', $this->isImportEnabled());
        $this->view->assign('enableExport', $this->isExportEnabled());
    }

    /**
     * @param int $id
     * @param bool $deleteUnused
     * @param bool $cancelPrevious
     */
    public function importAction($id, array $file, $deleteUnused, $cancelPrevious, Country $country = null)
    {
        $batch = $this->mapRequestToBatch($id, $file, $deleteUnused, $cancelPrevious, $country);

        try {
            $batch->validate();
        } catch (Exception $e) {
            $this->addFlashMessage($e->getMessage(), '', FlashMessage::ERROR);
            $this->redirect('index');
        }

        $extensionName = $this->getExtensionNameForLocalLang();

        if ($cancelPrevious) {
            /** @var Batch $previousBatch */
            foreach ($this->batchRepository->findOpenInFolder($id) as $previousBatch) {
                $previousBatch->cancle();
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
        $this->redirect('index');
    }

    public function exportAction()
    {
    }

    /**
     * @return string|void
     */
    public function errorAction()
    {
    }

    /**
     * @param string $reason
     */
    protected function forwardToErrorWithCannedMessage($reason)
    {
        $extensionName = $this->getExtensionNameForLocalLang();
        $this->addFlashMessage(
            LocalizationUtility::translate(sprintf('module.flash-messages.%s.content', $reason), $extensionName),
            LocalizationUtility::translate(sprintf('module.flash-messages.%s.title', $reason), $extensionName),
            FlashMessage::ERROR
        );
        $this->forward('error');
    }

    protected function getExtensionNameForLocalLang()
    {
        return 'nxgooglelocations';
    }

    /**
     * @return string
     */
    protected function getTabeNameForBatchRecords()
    {
        return 'tx_nxgooglelocations_domain_model_batch';
    }

    /**
     * @return string
     */
    abstract protected function getTableNameForLocationRecords();

    abstract protected function mapRequestToBatch(
        $id,
        array $file,
        $deleteUnused,
        $cancelPrevious,
        Country $country = null
    ): Batch;

    protected function isImportEnabled()
    {
        return empty($this->settings['disableImport']);
    }

    protected function isExportEnabled()
    {
        return empty($this->settings['disableExport']);
    }
}
