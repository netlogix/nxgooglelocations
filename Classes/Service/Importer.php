<?php

namespace Netlogix\Nxgooglelocations\Service;

use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
     * @var class-string
     */
    const FIELD_MAP_CLASSNAME = FieldMap::class;

    /**
     * @param int $storagePageId
     */
    public function __construct($storagePageId)
    {
        $this->storagePageId = $storagePageId;
        $this->fieldMap = GeneralUtility::makeInstance(static::FIELD_MAP_CLASSNAME);
        if (!($this->fieldMap instanceof FieldMap)) {
            throw new \Exception(
                sprintf(
                    'Field map must be of type %s, %s given.',
                    FieldMap::class,
                    static::FIELD_MAP_CLASSNAME
                ),
                1635428593
            );
        }
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
            return (int)(array_key_exists($uid,
                $dataHandler->substNEWwithIDs) ? $dataHandler->substNEWwithIDs[$uid] : $uid);
        }, array_keys($data[$recordTableName]));
    }

    /**
     * @param string $recordTableName
     * @param int $storagePageId
     * @param int ...$recordUids
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function removeRecordsExcept(string $recordTableName, int $storagePageId, int ...$recordUids): void
    {
        $recordUids[] = 0;

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($recordTableName);
        $query = $connection->createQueryBuilder();
        $expr = $query->expr();
        $delinquens = $query
            ->select('uid')
            ->from($recordTableName)
            ->where(
                $expr->eq('pid', $storagePageId),
                $expr->notIn('uid', $recordUids)
            )
            ->execute()
            ->fetchFirstColumn();

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
        $existingRecord = $this->getExistingRecord($recordTableName, $this->storagePageId, $tcaRecord);
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
        $existingRecord = $this->getExistingRecord($recordTableName, $this->storagePageId, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord[$this->fieldMap->latitude] = $existingRecord[$this->fieldMap->latitude];
            $tcaRecord[$this->fieldMap->longitude] = $existingRecord[$this->fieldMap->longitude];
        }
        return $tcaRecord;
    }

    /**
     * @param string $recordTableName
     * @param int $storagePageId
     * @param array $tcaRecord
     * @return array|null
     */
    abstract public function getExistingRecord(string $recordTableName, int $storagePageId, array $tcaRecord): ?array;
}
