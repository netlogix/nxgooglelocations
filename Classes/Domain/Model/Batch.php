<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Model;

use DateTime;
use Exception;
use Netlogix\Nxgooglelocations\Enumerations\BatchState;
use Netlogix\Nxgooglelocations\Service\BackendUserImpersonator;
use Netlogix\Nxgooglelocations\Service\GeoCoder;
use Netlogix\Nxgooglelocations\Service\Importer;
use Netlogix\Nxgooglelocations\Service\LocationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM as TYPO3;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Batch extends AbstractEntity
{
    protected string $fileName;

    protected string $fileContent;

    protected string $fileHash;

    #[TYPO3\Transient]
    protected ?BackendUserImpersonator $impersonator = null;

    #[TYPO3\Transient]
    protected ?GeoCoder $geoCoder = null;

    #[TYPO3\Transient]
    protected ?Importer $importer = null;

    #[TYPO3\Transient]
    protected ?LocationFactory $locationFactory = null;

    /**
     * @var callable
     */
    #[TYPO3\Transient]
    protected $callback;

    protected string $state = BatchState::NEW;

    protected int $amount = 0;

    protected int $position = 0;

    protected int $geocodingRequests = 0;

    protected ?DateTime $tstamp = null;

    /**
     * @var string[]
     */
    #[TYPO3\Transient]
    protected $serviceClasses = [
        GeoCoder::class => GeoCoder::class,
        Importer::class => Importer::class,
        LocationFactory::class => LocationFactory::class,
    ];

    public function __construct(
        protected string $apiKey,
        protected int $storagePageId,
        protected int $backendUserId,
        string $filePath,
        string $fileName = '',
        protected bool $deleteUnused = true
    ) {
        $this->fileName = pathinfo($fileName ?: $filePath, PATHINFO_BASENAME);
        $this->fileContent = base64_encode(file_get_contents(GeneralUtility::getFileAbsFileName($filePath)));
        $this->fileHash = sha1($this->fileContent);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function run(callable $callback = null): void
    {
        $this->callback = $callback;
        $this->setState(BatchState::VALIDATING);
        $this->validate();
        $this->setState(BatchState::GEOCODING);
        $tcaRecords = $this->collectTcaRecords();
        $this->setState(BatchState::PERSISTING);
        $this->executeDataHandler($tcaRecords);
        $this->setState(BatchState::CLOSED);
        $this->callback = null;
    }

    public function validate(): void
    {
        $filePath = $this->getTemporaryFilePath();
        $factory = $this->getLocationFactory();
        $factory->load($filePath);
        unlink($filePath);
        $factory->compareHeaderRows();
    }

    public function cancel(): void
    {
        $this->setState(BatchState::CLOSED);
    }

    protected function setState(string $state): void
    {
        $this->state = $state;
        $this->emitStateChange();
    }

    protected function setAmountAndResetPosition(int $amount): void
    {
        $this->amount = $amount;
        $this->position = 0;
        $this->emitStateChange();
    }

    protected function setPosition(int $position): void
    {
        $this->position = $position;
        $this->emitStateChange();
    }

    protected function emitStateChange(): void
    {
        $this->callback && call_user_func($this->callback, $this, $this->amount, $this->position, $this->state);
    }

    protected function getTemporaryFilePath(): string
    {
        $filePath = GeneralUtility::tempnam('location-batch-', pathinfo($this->fileName, PATHINFO_EXTENSION));
        GeneralUtility::writeFileToTypo3tempDir($filePath, $this->getFileContent());

        return $filePath;
    }

    protected function getFileContent(): string
    {
        return base64_decode($this->fileContent, true);
    }

    protected function collectTcaRecords(): array
    {
        $tcaRecords = $this->getLocationFactory()
            ->getRecordsForValidRows();
        $this->setAmountAndResetPosition(is_countable($tcaRecords) ? count($tcaRecords) : 0);

        $position = 0;
        foreach ($tcaRecords as $id => $tcaRecord) {
            $tcaRecords[$id] = $this->mapTcaRecord($tcaRecord);
            $this->setPosition(++$position);
        }

        return $tcaRecords;
    }

    protected function executeDataHandler(array $tcaRecords): void
    {
        $backendUserId = $this->backendUserId;
        $storagePageId = $this->storagePageId;
        $deleteUnused = $this->deleteUnused;

        $importer = $this->getImporter();

        $recordTableName = $this->getLocationFactory()
            ->getRecordTableName();

        $this->impersonator->runAsBackendUser(
            $backendUserId,
            static function () use ($importer, $recordTableName, $tcaRecords, $storagePageId, $deleteUnused): void {
                $recordUids = $importer->import($recordTableName, $storagePageId, $tcaRecords);
                if ($deleteUnused) {
                    $importer->removeRecordsExcept($recordTableName, $storagePageId, $recordUids);
                }
            }
        );
    }

    protected function mapTcaRecord($tcaRecord): array
    {
        $importer = $this->getImporter();
        $factory = $this->getLocationFactory();
        $coder = $this->getGeoCoder();

        $tcaRecord = $importer->writeExistingIdentifierToTcaRecord($factory->getRecordTableName(), $tcaRecord);
        if (!$coder->needsToBeGeoCoded($tcaRecord)) {
            $tcaRecord = $coder->setProbabilityToManually($tcaRecord);
        }

        $existingRecord = (array) $importer->getExistingRecord(
            $factory->getRecordTableName(),
            $this->storagePageId,
            $tcaRecord
        );

        if ($coder->needsToBeGeoCoded($tcaRecord)
            && !$coder->needsToBeGeoCoded($existingRecord)
            && $coder->getGeoCodingAddress($tcaRecord) === $coder->getGeoCodingAddress($existingRecord)
        ) {
            $tcaRecord = $importer->writeExistingCoordinatesToTcaRecord($factory->getRecordTableName(), $tcaRecord);
        }

        if ($coder->needsToBeGeoCoded($tcaRecord)) {
            try {
                ++$this->geocodingRequests;
                $geoCodingAddress = $coder->getGeoCodingAddress($tcaRecord);
                $codingResult = $coder->fetchCoordinatesForAddress($geoCodingAddress);
                $tcaRecord = $factory->writeCoordinatesToTcaRecord($tcaRecord, $codingResult);
            } catch (Exception) {
            }
        }

        return $tcaRecord;
    }

    protected function getGeoCoder(): GeoCoder
    {
        $this->initializeServices();

        return $this->geoCoder;
    }

    protected function getImporter(): Importer
    {
        $this->initializeServices();

        return $this->importer;
    }

    protected function getLocationFactory(): LocationFactory
    {
        $this->initializeServices();

        return $this->locationFactory;
    }

    protected function initializeServices(): void
    {
        if (!$this->impersonator instanceof \Netlogix\Nxgooglelocations\Service\BackendUserImpersonator) {
            $this->impersonator = GeneralUtility::makeInstance(BackendUserImpersonator::class);
        }

        if (!$this->geoCoder instanceof \Netlogix\Nxgooglelocations\Service\GeoCoder) {
            $this->geoCoder = GeneralUtility::makeInstance($this->serviceClasses[GeoCoder::class], $this->apiKey);
        }

        if (!$this->importer instanceof \Netlogix\Nxgooglelocations\Service\Importer) {
            $this->importer = GeneralUtility::makeInstance($this->serviceClasses[Importer::class], $this->storagePageId);
        }

        if (!$this->locationFactory instanceof \Netlogix\Nxgooglelocations\Service\LocationFactory) {
            $this->locationFactory = GeneralUtility::makeInstance($this->serviceClasses[LocationFactory::class]);
        }
    }
}
