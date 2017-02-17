<?php
namespace Netlogix\Nxgooglelocations\Service;

use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class Importer
{
    /**
     * @var int
     */
    protected $storagePageId;

    /**
     * @var FieldMap
     */
    protected $fieldMap;

    /**
     * @var string
     */
    protected $fieldMapClassName = FieldMap::class;

    /**
     * @param int $storagePageId
     */
    public function __construct($storagePageId)
    {
        $this->storagePageId = $storagePageId;
        $this->fieldMap = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class)->get($this->fieldMapClassName);
    }

    /**
     * @param string $recordTableName
     * @param int $storagePageId
     * @param array $tcaRecords
     * @return array<int>
     */
    public function import($recordTableName, $storagePageId, $tcaRecords)
    {
        $data = [$recordTableName => []];
        $commands = [$recordTableName => []];

        $count = 0;
        foreach ($tcaRecords as $tcaRecord) {
            $count++;
            $uid = $tcaRecord['uid'] ?: sprintf('NEW%s', GeneralUtility::shortMD5(__CLASS__ . $count));
            $data[$recordTableName][$uid] = $tcaRecord;
            $data[$recordTableName][$uid]['pid'] = $storagePageId;
            $data[$recordTableName][$uid][$GLOBALS['TCA'][$recordTableName]['ctrl']['languageField']] = -1;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        return array_map(function ($uid) use ($dataHandler) {
            return (int)(array_key_exists($uid, $dataHandler->substNEWwithIDs) ? $dataHandler->substNEWwithIDs[$uid] : $uid);
        }, array_keys($data[$recordTableName]));
    }

    /**
     * @param string $recordTableName
     * @param int $storagePageId
     * @param array <int> $recordUids
     */
    public function removeRecordsExcept($recordTableName, $storagePageId, $recordUids)
    {
        $recordUids[] = 0;

        $listOfRecordUids = join(',', array_map(function ($recordUid) {
            return (int)$recordUid;
        }, $recordUids));

        /** @var DatabaseConnection $database */
        $database = $GLOBALS['TYPO3_DB'];
        $database->sql_query('SET group_concat_max_len = 32768');
        $delinquens = GeneralUtility::intExplode(
            ',',
            current(
                current(
                    $database->exec_SELECTgetRows(
                        'GROUP_CONCAT(uid)',
                        $recordTableName,
                        sprintf('pid = %d AND uid NOT IN (%s) ', $storagePageId, $listOfRecordUids) . BackendUtility::deleteClause($recordTableName)
                    )
                )
            )
        );

        $commands = [$recordTableName => []];
        foreach ($delinquens as $delinquentUid) {
            $commands[$recordTableName][$delinquentUid]['delete'] = 1;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    /**
     * @param string $recordTableName
     * @param array $tcaRecord
     * @return array
     */
    public function writeExistingIdentifierToTcaRecord($recordTableName, array $tcaRecord)
    {
        $existingRecord = $this->getExistingRecord($recordTableName, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord['uid'] = $existingRecord['uid'];
        }
        return $tcaRecord;
    }

    /**
     * @param string $recordTableName
     * @param array $tcaRecord
     * @return array
     */
    public function writeExistingCoordinatesToTcaRecord($recordTableName, array $tcaRecord)
    {
        $existingRecord = $this->getExistingRecord($recordTableName, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord[$this->fieldMap->latitude] = $existingRecord[$this->fieldMap->latitude];
            $tcaRecord[$this->fieldMap->longitude] = $existingRecord[$this->fieldMap->longitude];
        }
        return $tcaRecord;
    }

    /**
     * @param array $tcaRecord
     * @return array
     */
    abstract public function getExistingRecord($recordTableName, array $tcaRecord);
}