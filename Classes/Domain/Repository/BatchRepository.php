<?php

namespace Netlogix\Nxgooglelocations\Domain\Repository;

use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @method null|Batch findOneByState(string $state)
 */
class BatchRepository extends Repository
{
    public function initializeObject()
    {
        $defaultQuerySettings = $this->objectManager->get(QuerySettingsInterface::class);
        assert($defaultQuerySettings instanceof QuerySettingsInterface);
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
    }

    /**
     * @param int $storagePageId
     * @return array|QueryResultInterface
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
}
