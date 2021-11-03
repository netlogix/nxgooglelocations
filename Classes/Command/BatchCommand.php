<?php

namespace Netlogix\Nxgooglelocations\Command;

use Netlogix\Nxgooglelocations\Domain\Model\Batch;
use Netlogix\Nxgooglelocations\Domain\Repository\BatchRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class BatchCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Import jobs from a given feed.');
    }

    public function injectBatchRepository(BatchRepository $batchRepository)
    {
        $this->batchRepository = $batchRepository;
    }

    /**
     * @param PersistenceManagerInterface $persistenceManager
     */
    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchRepository = $this->batchRepository;
        $persistenceManager = $this->persistenceManager;

        $this->getDatabaseConnection()
            ->executeStatement('SET SESSION wait_timeout = 3600');

        $batch = $batchRepository->findOneByState(Batch::STATE_NEW);
        if (!$batch instanceof Batch) {
            return 0;
        }

        $batch->run(static function ($batch, $amount, $position, $state) use ($batchRepository, $persistenceManager) {
            $batchRepository->update($batch);
            $persistenceManager->persistAll();
        });

        return 0;
    }

    public function getDatabaseConnection(): Connection
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $pool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
    }
}
