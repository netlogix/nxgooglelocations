<?php

namespace Netlogix\Nxgooglelocations\Service;

use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @var Worksheet
     */
    protected $templateSheet;

    /**
     * @var Worksheet
     */
    protected $contentSheet;

    public function __construct()
    {
        $this->fieldMap = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class)->get($this->fieldMapClassName);
        $this->reset();
    }

    public function reset()
    {
        $this->templateSheet = $this->getActiveSheetOfFile($this->templateFileName);
    }

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
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex) . ($rowIndex + 1);
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
     * @param CodingResult $codingResult
     * @return array
     */
    public function writeCoordinatesToTcaRecord($tcaRecord, $codingResult = null)
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
        return sprintf(
            'A%d:%s%d',
            $this->templateSheet->getHighestRow() + 1, $this->contentSheet->getHighestColumn(),
            $this->contentSheet->getHighestRow()
        );
    }

    /**
     * @param string $fileName
     * @return Worksheet
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
