<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Model;

use Netlogix\Nxgooglelocations\Service\GeoCoder;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * An accessible data structure for Google Geocoding results.
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
    /**
     * @var int
     */
    final public const PROBABILITY_ZERO_RESULTS = -1;

    /**
     * @var int
     */
    final public const PROBABILITY_MANUALLY_IMPORT = -255;

    /**
     * @var int
     */
    final public const PROBABILITY_MANUALLY_EDITOR = -256;

    protected $minProbability = 0;

    protected $maxProbability = 10;

    public function __construct(
        protected array $rawData
    ) {
    }

    public function __get($propertyName)
    {
        switch ($propertyName) {
            case 'rawData':
                return $this->rawData;
            case 'status':
                return (string) ObjectAccess::getPropertyPath($this->rawData, 'status');
            case 'formattedAddress':
            case 'addressResultFromGeocoding':
                return (string) ObjectAccess::getPropertyPath($this->rawData, 'results.0.formatted_address');
            case 'latitude':
                return (float) ObjectAccess::getPropertyPath($this->rawData, 'results.0.geometry.location.lat');
            case 'longitude':
                return (float) ObjectAccess::getPropertyPath($this->rawData, 'results.0.geometry.location.lng');
            case 'position':
                return [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                ];
            case 'probability':
                if ($this->status === GeoCoder::STATUS_ZERO_RESULTS) {
                    return self::PROBABILITY_ZERO_RESULTS;
                }

                return max(
                    $this->minProbability,
                    min(
                        $this->maxProbability,
                        is_countable(ObjectAccess::getPropertyPath($this->rawData, 'results')) ? count(
                            ObjectAccess::getPropertyPath($this->rawData, 'results')
                        ) : 0
                    )
                );
        }

        throw new InvalidSourceException('There is no property "' . $propertyName . '" in CodingResults.');
    }
}
