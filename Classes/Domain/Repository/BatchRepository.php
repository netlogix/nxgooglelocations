<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use function func_get_args;
use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @method null|Batch findOneByState(string $state)
 */
class BatchRepository extends Repository
{
    public function initializeObject(): void
    {
        /** @var QuerySettingsInterface $querySettings */
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param int $storagePageId
     * @return QueryResultInterface<Batch>
     */
    public function findOpenInFolder($storagePageId)
    {
        $query = $this->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setRespectStoragePage(true);
        $querySettings->setStoragePageIds([$storagePageId]);

        $query->setQuerySettings($querySettings);

        $query->matching($query->logicalNot($query->equals('state', Batch::STATE_CLOSED)));

        return $query->execute();
    }

    public function findOneByState(string $state): ?Batch
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }
}
