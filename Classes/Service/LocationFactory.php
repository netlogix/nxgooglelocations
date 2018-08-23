<?php
namespace Netlogix\Nxgooglelocations\Service;

use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use PHPExcel_Cell;
use PHPExcel_Exception;
use PHPExcel_IOFactory;
use PHPExcel_Reader_Abstract;
use PHPExcel_Reader_Exception;
use PHPExcel_Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Property\Exception\InvalidPropertyException;

abstract class LocationFactory
{
    protected $templateFileName = '/dev/null';

    /**
     * @var FieldMap
     */
    protected $fieldMap;

    /**
     * @var string
     */
    protected $fieldMapClassName = FieldMap::class;

    /**
     * @var string
     */
    protected $recordTableName = '';

    /**
     * @var array<string>
     */
    protected $columnNameMap = [
        'A' => 'title',
        'B' => 'address',
        'C' => 'alterantive_address',
        'D' => 'latitude',
        'E' => 'longitude',
    ];

    /**
     * @var PHPExcel_Worksheet
     */
    protected $templateSheet;

    /**
     * @var PHPExcel_Worksheet
     */
    protected $contentSheet;

    /**
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     */
    public function __construct()
    {
        $this->fieldMap = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class)->get($this->fieldMapClassName);
        $this->reset();
    }

    /**
     * @throws PHPExcel_Exception
     * @throws PHPExcel_Reader_Exception
     */
    public function reset()
    {
        $this->templateSheet = $this->getActiveSheetOfFile($this->templateFileName);
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
     * @throws \Exception
     */
    public function compareHeaderRows()
    {
        $template = $this->templateSheet->toArray();
        $content = $this->contentSheet->toArray();

        foreach ($template as $rowIndex => $rowData) {
            if (!self::containsData($rowData)) {
                continue;
            }
            foreach ($rowData as $columnIndex => $cellContent) {
                $coordinate = PHPExcel_Cell::stringFromColumnIndex($columnIndex) . ($rowIndex + 1);
                if ($template[$rowIndex][$columnIndex] != $content[$rowIndex][$columnIndex]) {
                    throw new \Exception(sprintf(
                        'Import header doesn\'t match import template at position "%s". Should be "%s" but is "%s".',
                        $coordinate,
                        $template[$rowIndex][$columnIndex],
                        $content[$rowIndex][$columnIndex]
                    ));
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getRecordsForValidRows()
    {
        $collection = $this->contentSheet->rangeToArray(
            $this->getDataRange(),
            $nullValue = null,
            $calculateFormulas = true,
            $formatData = true,
            $returnCellRef = true
        );
        $collection = array_filter($collection, [$this, 'containsData']);
        $collection = array_map([$this, 'mapDataRowToTcaRecord'], $collection);
        return array_filter($collection, [$this, 'containsData']);
    }

    /**
     * @param array $dataRow
     * @return array
     */
    public function mapDataRowToTcaRecord(array $dataRow)
    {
        $result = [];
        foreach ($this->columnNameMap as $tableColumnName => $tcaFieldName) {
            $result[$tcaFieldName] = $dataRow[$tableColumnName];
        }
        return $result;
    }

    /**
     * @param mixed $list
     * @return bool
     */
    public static function containsData($list)
    {
        return !!array_filter($list, function ($field) {
            if (is_array($field)) {
                return self::containsData($field);
            } else {
                return !!$field;
            }
        });
    }

    /**
     * @param array $tcaRecord
     * @param CodingResult|null $codingResult
     * @return array
     * @throws InvalidPropertyException
     */
    public function writeCoordinatesToTcaRecord(array $tcaRecord, CodingResult $codingResult = null)
    {
        $map = ['rawData', 'addressResultFromGeocoding', 'latitude', 'longitude', 'position', 'probability'];
        foreach ($map as $fieldName) {
            if ($this->fieldMap->__get($fieldName)) {
                $tcaRecord[$this->fieldMap->__get($fieldName)] = $codingResult->__get($fieldName);
                if (in_array($fieldName, ['rawData', 'position'])) {
                    $tcaRecord[$this->fieldMap->__get($fieldName)] = json_encode($tcaRecord[$this->fieldMap->__get($fieldName)]);
                }
            }
        }
        return $tcaRecord;
    }

    /**
     * @return string
     */
    public function getRecordTableName()
    {
        return $this->recordTableName;
    }

    /**
     * @return string
     */
    protected function getDataRange()
    {
        return sprintf('A%d:%s%d', $this->templateSheet->getHighestRow() + 1, $this->contentSheet->getHighestColumn(), $this->contentSheet->getHighestRow());
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
