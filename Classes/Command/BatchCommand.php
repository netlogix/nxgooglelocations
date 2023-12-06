<?php

declare(strict_types=1);

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
    public function __construct(
        private readonly BatchRepository $batchRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchRepository = $this->batchRepository;
        $persistenceManager = $this->persistenceManager;

        $this->getDatabaseConnection()
            ->executeStatement('SET SESSION wait_timeout = 3600');

        $batch = $batchRepository->findOneByState(Batch::STATE_NEW);
        if (!$batch instanceof Batch) {
            return 0;
        }

        $batch->run(static function ($batch, $amount, $position, $state) use (
            $batchRepository,
            $persistenceManager
        ): void {
            $batchRepository->update($batch);
            $persistenceManager->persistAll();
        });

        return 0;
    }

    public function getDatabaseConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
    }
}
