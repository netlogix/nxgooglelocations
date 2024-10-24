<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Repository;

use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use Netlogix\Nxgooglelocations\Enumerations\BatchState;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class BatchRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        assert($querySettings instanceof QuerySettingsInterface);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    public function findOpenInFolder(int $storagePageId): QueryResultInterface
    {
        $query = $this->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setRespectStoragePage(true);
        $querySettings->setStoragePageIds([$storagePageId]);

        $query->setQuerySettings($querySettings);

        $query->matching($query->logicalNot($query->equals('state', BatchState::CLOSED)));

        return $query->execute();
    }

    public function findOneByState(string $state): ?Batch
    {
        return $this->findOneBy([
            'state' => $state,
        ]);
    }
}
