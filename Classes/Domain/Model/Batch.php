<?php
namespace Netlogix\Nxgooglelocations\Domain\Model;

use Netlogix\Nxgooglelocations\Service\BackendUserImpersonator;
use Netlogix\Nxgooglelocations\Service\GeoCoder;
use Netlogix\Nxgooglelocations\Service\Importer;
use Netlogix\Nxgooglelocations\Service\LocationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class Batch extends AbstractEntity
{
    const STATE_NEW = 'new';
    const STATE_VALIDATING = 'validating';
    const STATE_GEOCODING = 'running';
    const STATE_PERSISTING = 'persisting';
    const STATE_CLOSED = 'closed';

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var int
     */
    protected $storagePageId;

    /**
     * @var int
     */
    protected $backendUserId;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $fileContent;

    /**
     * @var string
     */
    protected $fileHash;

    /**
     * @var bool
     */
    protected $deleteUnused;

    /**
     * @var BackendUserImpersonator
     * @transient
     */
    protected $impersonator;

    /**
     * @var GeoCoder
     * @transient
     */
    protected $geoCoder;

    /**
     * @var Importer
     * @transient
     */
    protected $importer;

    /**
     * @var LocationFactory
     * @transient
     */
    protected $locationFactory;

    /**
     * @var callable
     * @transient
     */
    protected $callback;

    /**
     * @var string
     */
    protected $state = self::STATE_NEW;

    /**
     * @var int
     */
    protected $amount = 0;

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @var int
     */
    protected $geocodingRequests = 0;

    /**
     * @var \DateTime
     */
    protected $tstamp;

    /**
     * @var array<string>
     * @transient
     */
    protected $serviceClasses = [
        GeoCoder::class => GeoCoder::class,
        Importer::class => Importer::class,
        LocationFactory::class => LocationFactory::class,
    ];

    /**
     * @param string $apiKey
     * @param int $storagePageId
     * @param int $backendUserId
     * @param string $filePath
     * @param string $fileName
     * @param bool $deleteUnused
     */
    public function __construct($apiKey, $storagePageId, $backendUserId, $filePath, $fileName = '', $deleteUnused = true)
    {
        $this->apiKey = $apiKey;
        $this->storagePageId = $storagePageId;
        $this->backendUserId = $backendUserId;
        $this->fileName = pathinfo($fileName ?: $filePath, PATHINFO_BASENAME);
        $this->fileContent = base64_encode(file_get_contents(GeneralUtility::getFileAbsFileName($filePath)));
        $this->fileHash = sha1($this->fileContent);
        $this->deleteUnused = !!$deleteUnused;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function run(callable $callback = null)
    {
        $this->callback = $callback;
        $this->setState(self::STATE_VALIDATING);
        $this->validate();
        $this->setState(self::STATE_GEOCODING);
        $tcaRecords = $this->collectTcaRecords();
        $this->setState(self::STATE_PERSISTING);
        $this->executeDataHandler($tcaRecords);
        $this->setState(self::STATE_CLOSED);
        $this->callback = null;
    }

    public function validate()
    {
        $filePath = $this->getTemporaryFilePath();
        $this->getLocationFactory()->load($filePath);
        unlink($filePath);
        $this->getLocationFactory()->compareHeaderRows();
    }

    public function cancle()
    {
        $this->setState(self::STATE_CLOSED);
    }

    protected function setState($state)
    {
        $this->state = $state;
        $this->emitStateChange();
    }

    protected function setAmountAndResetPosition($amount)
    {
        $this->amount = $amount;
        $this->position = 0;
        $this->emitStateChange();
    }

    protected function setPosition($position)
    {
        $this->position = $position;
        $this->emitStateChange();
    }

    protected function emitStateChange()
    {
        $this->callback && call_user_func($this->callback, $this, $this->amount, $this->position, $this->state);
    }

    protected function getTemporaryFilePath()
    {
        $filePath = sprintf(PATH_site . 'typo3temp/locations/l-%s-%s.%s', $this->fileHash, getmypid(), pathinfo($this->fileName, PATHINFO_EXTENSION));
        GeneralUtility::writeFileToTypo3tempDir($filePath, base64_decode($this->fileContent));
        return $filePath;
    }

    protected function collectTcaRecords()
    {
        $tcaRecords = $this->getLocationFactory()->getRecordsForValidRows();
        $this->setAmountAndResetPosition(count($tcaRecords));

        $position = 0;
        foreach ($tcaRecords as $id => $tcaRecord) {
            $tcaRecords[$id] = $this->mapTcaRecord($tcaRecord);
            $this->setPosition(++$position);
        }

        return $tcaRecords;
    }

    protected function executeDataHandler($tcaRecords)
    {
        $backendUserId = $this->backendUserId;
        $storagePageId = $this->storagePageId;
        $importer = $this->getImporter();
        $locationFactory = $this->getLocationFactory();
        $deleteUnused = $this->deleteUnused;
        $this->impersonator->runAsBackendUser($backendUserId, function () use ($importer, $locationFactory, $tcaRecords, $storagePageId, $deleteUnused) {
            $recordUids = $importer->import($locationFactory->getRecordTableName(), $storagePageId, $tcaRecords);
            if ($deleteUnused) {
                $importer->removeRecordsExcept($locationFactory->getRecordTableName(), $storagePageId, $recordUids);
            }
        });
    }

    /**
     * @param $tcaRecord
     * @return array
     */
    protected function mapTcaRecord($tcaRecord)
    {
        $tcaRecord = $this->getImporter()->writeExistingIdentifierToTcaRecord($this->getLocationFactory()->getRecordTableName(), $tcaRecord);
        if (!$this->getGeoCoder()->needsToBeGeoCoded($tcaRecord)) {
            $tcaRecord = $this->getGeoCoder()->setProbabilityToManually($tcaRecord);
        }

        $existingRecord = (array)$this->getImporter()->getExistingRecord($this->getLocationFactory()->getRecordTableName(), $this->storagePageId, $tcaRecord);

        if ($this->getGeoCoder()->needsToBeGeoCoded($tcaRecord)
            && !$this->getGeoCoder()->needsToBeGeoCoded($existingRecord)
            && $this->getGeoCoder()->getGeoCodingAddress($tcaRecord) === $this->getGeoCoder()->getGeoCodingAddress($existingRecord)
        ) {
            $tcaRecord = $this->getImporter()->writeExistingCoordinatesToTcaRecord($this->getLocationFactory()->getRecordTableName(), $tcaRecord);
        }

        if ($this->getGeoCoder()->needsToBeGeoCoded($tcaRecord)) {
            try {
                $this->geocodingRequests++;
                $geoCodingAddress = $this->getGeoCoder()->getGeoCodingAddress($tcaRecord);
                $codingResult = $this->getGeoCoder()->fetchCoordinatesForAddress($geoCodingAddress);
                $tcaRecord = $this->getLocationFactory()->writeCoordinatesToTcaRecord($tcaRecord, $codingResult);
            } catch (\Exception $e) {

            }
        }

        return $tcaRecord;
    }

    protected function getGeoCoder()
    {
        $this->initializeServices();
        return $this->geoCoder;
    }

    protected function getImporter()
    {
        $this->initializeServices();
        return $this->importer;
    }

    protected function getLocationFactory()
    {
        $this->initializeServices();
        return $this->locationFactory;
    }

    protected function initializeServices()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        if (!$this->impersonator) {
            $this->impersonator = $objectManager->get(BackendUserImpersonator::class);
        }
        if (!$this->geoCoder) {
            $this->geoCoder = $objectManager->get($this->serviceClasses[GeoCoder::class], $this->apiKey);
        }
        if (!$this->importer) {
            $this->importer = $objectManager->get($this->serviceClasses[Importer::class], $this->storagePageId);
        }
        if (!$this->locationFactory) {
            $this->locationFactory = $objectManager->get($this->serviceClasses[LocationFactory::class]);
        }
    }
}