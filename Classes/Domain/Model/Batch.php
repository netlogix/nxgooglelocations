<?php
namespace Netlogix\Nxgooglelocations\Domain\Model;

use Netlogix\Nxgooglelocations\Service\BackendUserImpersonator;
use Netlogix\Nxuvexdealerlocator\Service\GeoCoder;
use Netlogix\Nxuvexdealerlocator\Service\Importer;
use Netlogix\Nxuvexdealerlocator\Service\LocationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class Batch
{
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
     * @param string $apiKey
     * @param int $storagePageId
     * @param int $backendUserId
     * @param string $filePath
     */
    public function __construct($apiKey, $storagePageId, $backendUserId, $filePath)
    {
        $this->apiKey = $apiKey;
        $this->storagePageId = $storagePageId;
        $this->backendUserId = $backendUserId;
        $this->fileName = pathinfo($filePath, PATHINFO_BASENAME);
        $this->fileContent = base64_encode(file_get_contents(GeneralUtility::getFileAbsFileName($filePath)));
        $this->fileHash = sha1($this->fileContent);
    }

    public function run()
    {
        $this->initializeServices();
        $filePath = $this->getTemporaryFilePath();
        $this->locationFactory->load($filePath);
        unset($filePath);
        $this->locationFactory->compareHeaderRows();
        $tcaRecords = $this->collectTcaRecords();
        $this->executeDataHandler($tcaRecords);
    }

    protected function initializeServices()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $this->impersonator = $objectManager->get(BackendUserImpersonator::class);
        $this->geoCoder = $objectManager->get(GeoCoder::class, $this->apiKey);
        $this->importer = $objectManager->get(Importer::class, $this->storagePageId);
        $this->locationFactory = $objectManager->get(LocationFactory::class);
    }

    protected function getTemporaryFilePath()
    {
        $filePath = sprintf(PATH_site . 'typo3temp/locations/%s.%s.%s', $this->fileHash, getmypid(), pathinfo($this->fileName, PATHINFO_EXTENSION));
        GeneralUtility::writeFileToTypo3tempDir($filePath, base64_decode($this->fileContent));
        return $filePath;
    }

    protected function collectTcaRecords()
    {
        $tcaRecords = $this->locationFactory->getRecordsForValidRows();

        foreach ($tcaRecords as $id => $tcaRecord) {
            $tcaRecords[$id] = $this->mapTcaRecord($tcaRecord);
        }

        return $tcaRecords;
    }

    protected function executeDataHandler($tcaRecords)
    {
        $backendUserId = $this->backendUserId;
        $storagePageId = $this->storagePageId;
        $importer = $this->importer;
        $locationFactory = $this->locationFactory;
        $this->impersonator->runAsBackendUser($backendUserId, function () use ($importer, $locationFactory, $tcaRecords, $storagePageId) {
            $recordUids = $importer->import($locationFactory->getRecordTableName(), $storagePageId, $tcaRecords);
            $importer->removeRecordsExcept($locationFactory->getRecordTableName(), $storagePageId, $recordUids);
        });
    }

    /**
     * @param $tcaRecord
     * @return array
     */
    protected function mapTcaRecord($tcaRecord)
    {
        $tcaRecord = $this->importer->writeExistingIdentifierToTcaRecord($this->locationFactory->getRecordTableName(), $tcaRecord);
        $existingRecord = (array)$this->importer->getExistingRecord($this->locationFactory->getRecordTableName(), $this->storagePageId, $tcaRecord);

        if ($this->geoCoder->needsToBeGeoCoded($tcaRecord)
            && !$this->geoCoder->needsToBeGeoCoded($existingRecord)
            && $this->geoCoder->getGeoCodingAddress($tcaRecord) === $this->geoCoder->getGeoCodingAddress($existingRecord)
        ) {
            $tcaRecord = $this->importer->writeExistingCoordinatesToTcaRecord($this->locationFactory->getRecordTableName(), $tcaRecord);
        }

        if ($this->geoCoder->needsToBeGeoCoded($tcaRecord)) {
            try {
                $geoCodingAddress = $this->geoCoder->getGeoCodingAddress($tcaRecord);
                $codingResult = $this->geoCoder->fetchCoordinatesForAddress($geoCodingAddress);
                $tcaRecord = $this->locationFactory->writeCoordinatesToTcaRecord($tcaRecord, $codingResult);
            } catch (\Exception $e) {

            }
        }

        return $tcaRecord;
    }
}