<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class Importer
{
    /**
     * @var FieldMap
     */
    protected static $fieldMap;

    /**
     * @var class-string
     */
    public const FIELD_MAP_CLASSNAME = FieldMap::class;

    /**
     * @param int $storagePageId
     */
    public function __construct(
        protected $storagePageId
    ) {
        $this->fieldMap = GeneralUtility::makeInstance(static::FIELD_MAP_CLASSNAME);
        if (!($this->fieldMap instanceof FieldMap)) {
            throw new Exception(
                sprintf('Field map must be of type %s, %s given.', FieldMap::class, static::FIELD_MAP_CLASSNAME),
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
        $data = [
            $recordTableName => [],
        ];
        $commands = [
            $recordTableName => [],
        ];

        $count = 0;
        foreach ($tcaRecords as $tcaRecord) {
            ++$count;
            $uid = $tcaRecord['uid'] ?: sprintf('NEW%s', substr(md5(self::class . $count), 0, 10));
            $data[$recordTableName][$uid] = $tcaRecord;
            $data[$recordTableName][$uid]['pid'] = $storagePageId;
            $data[$recordTableName][$uid][$GLOBALS['TCA'][$recordTableName]['ctrl']['languageField']] = -1;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        return array_map(static fn ($uid): int => (int) (array_key_exists(
            $uid,
            $dataHandler->substNEWwithIDs
        ) ? $dataHandler->substNEWwithIDs[$uid] : $uid), array_keys($data[$recordTableName]));
    }

    public function removeRecordsExcept(string $recordTableName, int $storagePageId, array $recordUids = []): void
    {
        $recordUids[] = 0;

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($recordTableName);
        $query = $connection->createQueryBuilder();
        $expr = $query->expr();
        $delinquens = $query
            ->select('uid')
            ->from($recordTableName)
            ->where($expr->eq('pid', $storagePageId), $expr->notIn('uid', $recordUids))
            ->executeQuery()
            ->fetchFirstColumn();

        $commands = [
            $recordTableName => [],
        ];
        foreach ($delinquens as $delinquentUid) {
            $commands[$recordTableName][$delinquentUid]['delete'] = 1;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    /**
     * @return array
     */
    public function writeExistingIdentifierToTcaRecord(string $recordTableName, array $tcaRecord)
    {
        $existingRecord = $this->getExistingRecord($recordTableName, $this->storagePageId, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord['uid'] = $existingRecord['uid'];
        }

        return $tcaRecord;
    }

    /**
     * @return array
     */
    public function writeExistingCoordinatesToTcaRecord(string $recordTableName, array $tcaRecord)
    {
        $existingRecord = $this->getExistingRecord($recordTableName, $this->storagePageId, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord[$this->fieldMap->latitude] = $existingRecord[$this->fieldMap->latitude];
            $tcaRecord[$this->fieldMap->longitude] = $existingRecord[$this->fieldMap->longitude];
        }

        return $tcaRecord;
    }

    abstract public function getExistingRecord(string $recordTableName, int $storagePageId, array $tcaRecord): ?array;
}
