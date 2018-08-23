<?php
namespace Netlogix\Nxgooglelocations\Domain\Model;

/**
 * @property string rawData
 * @property string addressToDisplay
 * @property string addressToGeocode
 * @property string addressResultFromGeocoding
 * @property string latitude
 * @property string longitude
 * @property string position
 * @property string probability
 */
abstract class FieldMap
{
    protected $fieldMap = [
        'rawData' => 'tx_nxgooglelocations_raw_data',
        'addressToDisplay' => 'tx_nxgooglelocations_display_address',
        'addressToGeocode' => 'tx_nxgooglelocations_geocoding_address',
        'addressResultFromGeocoding' => 'tx_nxgooglelocations_formated_address',
        'latitude' => 'tx_nxgooglelocations_latitude',
        'longitude' => 'tx_nxgooglelocations_longitude',
        'position' => 'tx_nxgooglelocations_position',
        'probability' => 'tx_nxgooglelocations_probability',
    ];

    public function __get($propertyName)
    {
        return $this->fieldMap[$propertyName];
    }
}
