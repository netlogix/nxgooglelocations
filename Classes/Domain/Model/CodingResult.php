<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Model;

use Netlogix\Nxgooglelocations\Enumerations\CodingResultProbability;
use Netlogix\Nxgooglelocations\Service\GeoCoderStatus;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * An accessible data structure for Google geocoding results.
 *
 * @property array rawData
 * @property string status
 * @property string formattedAddress
 * @property string addressResultFromGeocoding
 * @property float latitude
 * @property float longitude
 * @property array position
 * @property int probability
 */
class CodingResult
{
    protected int $minProbability = 0;

    protected int $maxProbability = 10;

    public function __construct(
        protected array $rawData
    ) {
    }

    public function __get($propertyName)
    {
        return match ($propertyName) {
            'rawData' => $this->rawData,
            'status' => (string) ObjectAccess::getPropertyPath($this->rawData, 'status'),
            'formattedAddress', 'addressResultFromGeocoding' => (string) ObjectAccess::getPropertyPath(
                $this->rawData,
                'results.0.formatted_address'
            ),
            'latitude' => (float) ObjectAccess::getPropertyPath($this->rawData, 'results.0.geometry.location.lat'),
            'longitude' => (float) ObjectAccess::getPropertyPath($this->rawData, 'results.0.geometry.location.lng'),
            'position' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'probability' => ($this->status === GeoCoderStatus::ZERO_RESULTS)
                ? CodingResultProbability::ZERO_RESULTS
                : max(
                    $this->minProbability,
                    min(
                        $this->maxProbability,
                        is_countable(ObjectAccess::getPropertyPath($this->rawData, 'results')) ? count(
                            ObjectAccess::getPropertyPath($this->rawData, 'results')
                        ) : 0
                    )
                ),
            default => throw new InvalidSourceException(
                'There is no property "' . $propertyName . '" in CodingResults.'
            ),
        };
    }
}
