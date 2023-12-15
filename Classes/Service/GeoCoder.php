<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use Netlogix\Nxgooglelocations\Enumerations\CodingResultProbability;
use Netlogix\Nxgooglelocations\Enumerations\GeoCoderStatus;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

abstract class GeoCoder
{
    /**
     * @var string
     */
    final public const FETCH_URL = 'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s';

    /**
     * @var FieldMap
     */
    protected $fieldMap;

    /**
     * @var string
     */
    protected $fieldMapClassName = FieldMap::class;

    /**
     * The number of geocoding results must be lower than this in order to make an existing
     * record count as "doesn't need to be geocoded".
     */
    protected int $probabilityThreshold = 1;

    public function __construct(
        protected string $apiKey
    ) {
        $this->fieldMap = GeneralUtility::makeInstance($this->fieldMapClassName);
    }

    public function getGeoCodingAddress(array $tcaRecord): string
    {
        return $tcaRecord[$this->fieldMap->addressToGeocode] ?: $tcaRecord[$this->fieldMap->addressToDisplay];
    }

    public function needsToBeGeoCoded(array $tcaRecord): bool
    {
        return (!$tcaRecord[$this->fieldMap->latitude] && !$tcaRecord[$this->fieldMap->longitude]) || ($tcaRecord[$this->fieldMap->probability] > $this->probabilityThreshold);
    }

    public function setProbabilityToManually(array $tcaRecord): array
    {
        $tcaRecord[$this->fieldMap->probability] = CodingResultProbability::MANUALLY_IMPORT;

        return $tcaRecord;
    }

    /**
     * TODO: Add some caching
     */
    public function fetchCoordinatesForAddress($address): CodingResult
    {
        $urlWithApiKey = sprintf(self::FETCH_URL, urlencode((string) $address), urlencode($this->apiKey));
        $geocode = json_decode((string) GeneralUtility::getUrl($urlWithApiKey), true, 512, JSON_THROW_ON_ERROR);
        $status = ObjectAccess::getPropertyPath($geocode, 'status');
        return match ($status) {
            GeoCoderStatus::OK, GeoCoderStatus::ZERO_RESULTS => new CodingResult($geocode),
            default => throw new Exception(
                'An error occurred: ' . json_encode(
                    array_filter(
                        [$status, ObjectAccess::getPropertyPath($geocode, 'error_message')],
                        static fn($value): bool => (bool)$value
                    ),
                    JSON_THROW_ON_ERROR
                )
            ),
        };
    }
}
