<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use Exception;
use Netlogix\Nxgooglelocations\Domain\Model\CodingResult;
use Netlogix\Nxgooglelocations\Domain\Model\FieldMap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

abstract class GeoCoder
{
    /**
     * @var string
     */
    final public const FETCH_URL = 'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s';

    /**
     * @var string
     */
    final public const STATUS_OK = 'OK';

    /**
     * @var string
     */
    final public const STATUS_ZERO_RESULTS = 'ZERO_RESULTS';

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
     * record count as "doesn't need to be geocoded"
     *
     * @var int
     */
    protected $probabilityThreshold = 1;

    /**
     * @param string $apiKey
     */
    public function __construct(
        protected $apiKey
    ) {
        $this->fieldMap = GeneralUtility::makeInstance($this->fieldMapClassName);
    }

    /**+
     * @param array $tcaRecord
     * @return string
     */
    public function getGeoCodingAddress(array $tcaRecord)
    {
        return $tcaRecord[$this->fieldMap->addressToGeocode] ?: $tcaRecord[$this->fieldMap->addressToDisplay];
    }

    public function needsToBeGeoCoded(array $tcaRecord): bool
    {
        return (!$tcaRecord[$this->fieldMap->latitude] && !$tcaRecord[$this->fieldMap->longitude]) || ($tcaRecord[$this->fieldMap->probability] > $this->probabilityThreshold);
    }

    /**
     * @return array
     */
    public function setProbabilityToManually(array $tcaRecord)
    {
        $tcaRecord[$this->fieldMap->probability] = CodingResult::PROBABILITY_MANUALLY_IMPORT;

        return $tcaRecord;
    }

    /**
     * TODO: Add some caching
     *
     * @return CodingResult
     */
    public function fetchCoordinatesForAddress($address)
    {
        $urlWithApiKey = sprintf(self::FETCH_URL, urlencode((string) $address), urlencode($this->apiKey));
        $geocode = json_decode((string) GeneralUtility::getUrl($urlWithApiKey), true, 512, JSON_THROW_ON_ERROR);
        $status = ObjectAccess::getPropertyPath($geocode, 'status');
        switch ($status) {
            case self::STATUS_OK:
            case self::STATUS_ZERO_RESULTS:
                return new CodingResult($geocode);
            default:
                $message = json_encode(
                    array_filter(
                        [$status, ObjectAccess::getPropertyPath($geocode, 'error_message')],
                        static fn ($value): bool => (bool) $value
                    ),
                    JSON_THROW_ON_ERROR
                );

                throw new Exception('An error occured: ' . $message);
        }
    }
}
