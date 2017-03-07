<?php
namespace Netlogix\Nxgooglelocations\Command;

use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use Netlogix\Nxgooglelocations\Domain\Repository\BatchRepository;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class BatchCommandController extends CommandController
{
    /**
     * @var BatchRepository
     */
    protected $batchRepository;

    /**
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    public function injectBatchRepository(BatchRepository $batchRepository) {
        $this->batchRepository = $batchRepository;
    }

    /**
     * @param PersistenceManagerInterface $persistenceManager
     */
    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function runScheduledCommand()
    {
        $batchRepository = $this->batchRepository;
        $persistenceManager = $this->persistenceManager;

        /** @var Batch $batch */
        $batch = $batchRepository->findOneByState(Batch::STATE_NEW);
        if (!$batch) {
            return;
        }

        $batch->run(function($batch, $amount, $position, $state) use ($batchRepository, $persistenceManager) {
            $batchRepository->update($batch);
            $persistenceManager->persistAll();
        });
    }

}
