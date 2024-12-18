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
    protected ?FieldMap $fieldMap;

    /**
     * @var class-string
     */
    public const FIELD_MAP_CLASSNAME = FieldMap::class;

    public function __construct(
        protected int $storagePageId
    ) {
        $this->fieldMap = GeneralUtility::makeInstance(static::FIELD_MAP_CLASSNAME);
        if (!($this->fieldMap instanceof FieldMap)) {
            throw new Exception(
                sprintf('Field map must be of type %s, %s given.', FieldMap::class, static::FIELD_MAP_CLASSNAME),
                1635428593
            );
        }
    }

    public function import(string $recordTableName, int $storagePageId, array $tcaRecords): array
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
            $uid = array_key_exists('uid', $tcaRecord) && $tcaRecord['uid'] !== ''
                ? $tcaRecord['uid']
                : sprintf('NEW%s', substr(md5(self::class . $count), 0, 10));
            $data[$recordTableName][$uid] = $tcaRecord;
            $data[$recordTableName][$uid]['pid'] = $storagePageId;
            $data[$recordTableName][$uid]['sys_language_uid'] = -1;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->isImporting = true;
        $dataHandler->enableLogging = false;
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
        $dataHandler->isImporting = true;
        $dataHandler->enableLogging = false;
        $dataHandler->start([], $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    public function writeExistingIdentifierToTcaRecord(string $recordTableName, array $tcaRecord): array
    {
        $existingRecord = $this->getExistingRecord($recordTableName, $this->storagePageId, $tcaRecord);
        if ($existingRecord) {
            $tcaRecord['uid'] = $existingRecord['uid'];
        }

        return $tcaRecord;
    }

    public function writeExistingCoordinatesToTcaRecord(string $recordTableName, array $tcaRecord): array
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
