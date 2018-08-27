<?php

namespace Netlogix\Nxgooglelocations\Service;

use PHPExcel_Exception;
use PHPExcel_IOFactory;
use PHPExcel_Reader_Abstract;
use PHPExcel_Reader_Exception;
use PHPExcel_Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExcelServiceTrait
{
    /**
     * @var PHPExcel_Worksheet
     */
    protected $templateSheet;

    /**
     * @var PHPExcel_Worksheet
     */
    protected $contentSheet;

    /**
     * @param string $templateFileName
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     */
    public function resetTemplateSheet(string $templateFileName)
    {
        $this->templateSheet = $this->getActiveSheetOfFile($templateFileName);
    }

    /**
     * @param $fileName
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     */
    public function load($fileName)
    {
        $this->contentSheet = $this->getActiveSheetOfFile($fileName);
    }

    /**
     * @param string $fileName
     * @return PHPExcel_Worksheet
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     */
    protected function getActiveSheetOfFile($fileName)
    {
        $fileName = GeneralUtility::getFileAbsFileName($fileName);
        $reader = PHPExcel_IOFactory::createReaderForFile($fileName);
        if ($reader instanceof PHPExcel_Reader_Abstract) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($fileName)->getActiveSheet();
    }
}
