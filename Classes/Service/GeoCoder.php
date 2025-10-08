<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use Netlogix\Nxgooglelocations\Enumeration\CodingResultProbability;
use Netlogix\Nxgooglelocations\Enumeration\GeoCoderStatus;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

abstract class GeoCoder
{
    final public const FETCH_URL = 'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s';

    protected FieldMap $fieldMap;

    protected string $fieldMapClassName = FieldMap::class;

    /**
     * The number of geocoding results must be lower than this in order to make an existing
     * record count as "doesn't need to be geocoded".
     */
    protected int $probabilityThreshold = 1;

    /**
     * @var array<CodingResult>
     */
    protected array $codingResultCache = [];

    public function __construct(
        protected string $apiKey
    ) {
        $this->fieldMap = GeneralUtility::makeInstance($this->fieldMapClassName);
    }

    public function getGeoCodingAddress(array $tcaRecord): string
    {
        if (
            array_key_exists($this->fieldMap->addressToGeocode, $tcaRecord) &&
            $tcaRecord[$this->fieldMap->addressToGeocode] !== ''
        ) {
            return $tcaRecord[$this->fieldMap->addressToGeocode];
        }

        if (
            array_key_exists($this->fieldMap->addressToDisplay, $tcaRecord) &&
            $tcaRecord[$this->fieldMap->addressToDisplay] !== ''
        ) {
            return $tcaRecord[$this->fieldMap->addressToDisplay];
        }

        return '';
    }

    public function needsToBeGeoCoded(array $tcaRecord): bool
    {
        $hasLatitude = array_key_exists($this->fieldMap->latitude, $tcaRecord)
            && $tcaRecord[$this->fieldMap->latitude] !== '';

        $hasLongitude = array_key_exists($this->fieldMap->longitude, $tcaRecord)
            && $tcaRecord[$this->fieldMap->longitude] !== '';

        $probability = array_key_exists($this->fieldMap->probability, $tcaRecord)
            ? ($tcaRecord[$this->fieldMap->probability] ?: 0)
            : 0;

        return (!$hasLatitude && !$hasLongitude) || ($probability > $this->probabilityThreshold);
    }

    public function setProbabilityToManually(array $tcaRecord): array
    {
        $tcaRecord[$this->fieldMap->probability] = CodingResultProbability::MANUALLY_IMPORT->value;

        return $tcaRecord;
    }

    public function fetchCoordinatesForAddress($address): CodingResult
    {
        $addressHash = sha1($address);

        if (array_key_exists($addressHash, $this->codingResultCache)) {
            return $this->codingResultCache[$addressHash];
        }

        $requestUrl = sprintf(self::FETCH_URL, urlencode($address), urlencode((string) $this->apiKey));
        $rawCodingResult = json_decode(
            (string) GeneralUtility::getUrl($requestUrl),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $status = GeoCoderStatus::tryFrom(ObjectAccess::getPropertyPath($rawCodingResult, 'status'));

        switch ($status) {
            case GeoCoderStatus::OK:
            case GeoCoderStatus::ZERO_RESULTS:
                $this->codingResultCache[$addressHash] = new \Netlogix\Nxuvexdealerlocator\Domain\Model\CodingResult($rawCodingResult);
                return $this->codingResultCache[$addressHash];
            default:
                throw new Exception('An error occured: ' . json_encode(
                    array_filter(
                        [$status->value, ObjectAccess::getPropertyPath($rawCodingResult, 'error_message')],
                        fn($value): bool => (bool) $value,
                    ),
                    JSON_THROW_ON_ERROR,
                ));
        }
    }
}
