<?php

namespace Netlogix\Nxgooglelocations\Service;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExcelServiceTrait
{
    /**
     * @var Worksheet
     */
    protected $templateSheet;

    /**
     * @var Worksheet
     */
    protected $contentSheet;

    /**
     * @param string $templateFileName
     * @throws SpreadsheetException
     * @throws ReaderException
     */
    public function resetTemplateSheet(string $templateFileName)
    {
        $this->templateSheet = $this->getActiveSheetOfFile($templateFileName);
    }

    /**
     * @param $fileName
     * @throws SpreadsheetException
     * @throws ReaderException
     */
    public function load($fileName)
    {
        $this->contentSheet = $this->getActiveSheetOfFile($fileName);
    }

    /**
     * @param string $fileName
     * @return Worksheet
     * @throws SpreadsheetException
     * @throws ReaderException
     */
    protected function getActiveSheetOfFile($fileName)
    {
        $fileName = GeneralUtility::getFileAbsFileName($fileName);
        $reader = IOFactory::createReaderForFile($fileName);
        if ($reader instanceof BaseReader) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($fileName)->getActiveSheet();
    }
}
