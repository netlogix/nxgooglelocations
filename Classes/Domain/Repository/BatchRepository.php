<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Repository;

use function func_get_args;
use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @method null|Batch findOneByState(string $state)
 */
class BatchRepository extends Repository
{
    public $objectManager;

    public function initializeObject(): void
    {
        $defaultQuerySettings = $this->objectManager->get(QuerySettingsInterface::class);
        assert($defaultQuerySettings instanceof QuerySettingsInterface);
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
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
