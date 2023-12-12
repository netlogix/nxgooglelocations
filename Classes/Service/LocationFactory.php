<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class LocationFactory
{
    use ExcelServiceTrait;

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

    public function __construct()
    {
        $this->fieldMap = GeneralUtility::makeInstance($this->fieldMapClassName);
        $this->resetTemplateSheet($this->templateFileName);
    }

    public function compareHeaderRows()
    {
        $template = $this->getHeaderRowsFromTemplate();
        $content = $this->contentSheet->toArray();

        foreach ($template as $rowIndex => $rowData) {
            foreach ($rowData as $columnIndex => $cellContent) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex) . ($rowIndex + 1);
                if ($template[$rowIndex][$columnIndex] !== $content[$rowIndex][$columnIndex]) {
                    throw new Exception(sprintf(
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
        $collection = array_filter($collection, $this->containsData(...));
        $collection = array_map($this->mapDataRowToTcaRecord(...), $collection);

        return array_filter($collection, $this->containsData(...));
    }

    /**
     * @return array
     */
    public function mapDataRowToTcaRecord(array $dataRow)
    {
        $result = [];
        foreach ($this->columnNameMap as $tableColumnName => $tcaFieldName) {
            if ($dataRow[$tableColumnName] !== null) {
                $result[$tcaFieldName] = $dataRow[$tableColumnName];
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    public static function containsData(mixed $list)
    {
        return (bool) array_filter($list, static function ($field): bool {
            if (is_array($field)) {
                return self::containsData($field);
            }

            return (bool) $field;
        });
    }

    /**
     * @return array
     */
    public function writeCoordinatesToTcaRecord(array $tcaRecord, CodingResult $codingResult = null)
    {
        $map = ['rawData', 'addressResultFromGeocoding', 'latitude', 'longitude', 'position', 'probability'];
        foreach ($map as $fieldName) {
            if ($this->fieldMap->__get($fieldName)) {
                $tcaRecord[$this->fieldMap->__get($fieldName)] = $codingResult->__get($fieldName);
                if (in_array($fieldName, ['rawData', 'position'], true)) {
                    $tcaRecord[$this->fieldMap->__get($fieldName)] = json_encode(
                        $tcaRecord[$this->fieldMap->__get($fieldName)],
                        JSON_THROW_ON_ERROR
                    );
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
        $firstContentRow = max(...array_keys($this->getHeaderRowsFromTemplate())) + 2;

        return sprintf(
            'A%d:%s%d',
            $firstContentRow,
            $this->contentSheet->getHighestColumn(),
            $this->contentSheet->getHighestRow()
        );
    }

    /**
     * The Sheet::getHighestRow() consumes excel meta data. If there's an empty line
     * that has been touched in any way before, e.g. by placing the cursor in it before
     * saving, this line gets counted as well.
     * This function actually counts non-empty lines.
     *
     * @return array
     */
    protected function getHeaderRowsFromTemplate()
    {
        return array_filter(
            $this->templateSheet->toArray(),
            static fn (array $rowData): bool => self::containsData($rowData)
        );
    }
}
