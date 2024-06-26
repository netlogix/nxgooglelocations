<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExcelServiceTrait
{
    protected ?Worksheet $templateSheet = null;

    protected ?Worksheet $contentSheet = null;

    public function resetTemplateSheet(string $templateFileName): void
    {
        $this->templateSheet = $this->getActiveSheetOfFile($templateFileName);
    }

    public function load($fileName): void
    {
        $this->contentSheet = $this->getActiveSheetOfFile($fileName);
    }

    protected function getActiveSheetOfFile(string $fileName): Worksheet
    {
        $fileName = GeneralUtility::getFileAbsFileName($fileName);
        $reader = IOFactory::createReaderForFile($fileName);
        if ($reader instanceof BaseReader) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($fileName)
            ->getActiveSheet();
    }
}
