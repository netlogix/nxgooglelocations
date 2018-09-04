<?php

namespace Netlogix\Nxgooglelocations\Domain\Service;

use Netlogix\Nxgooglelocations\Hooks\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder as CoreQueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class DistanceQueryFactory
{
	const DISTANCE_FACTOR_FOR_KM = 6371;

	const DISTANCE_FACTOR_FOR_MILES = 3959;

	const QUERY_TEMPLATE = <<<'MySQL'
		(
			{distanceFactor}
			* ACOS(
				COS(radians( {center.latitude} ))
				* COS(radians(
					IF (NOT {marker.zerozero}, {marker.latitude}, {antipode.latitude})
				))
				* COS(radians( {center.longitude} ) - radians(
					IF (NOT {marker.zerozero}, {marker.longitude}, {antipode.longitude})
				))
				+ SIN(radians( {center.latitude} ))
				* SIN(radians(
					IF (NOT {marker.zerozero}, {marker.latitude}, {antipode.latitude})
				))
			)
		) AS {result.calculatedDistance}
MySQL;

	protected $tableName = '';

	protected $latitudePropertyName = '';

	protected $longitudePropertyName = '';

	public function __construct(
		string $tableName,
		string $latitudePropertyName,
		string $longitudePropertyName
	) {
		$this->tableName = $tableName;
		$this->latitudePropertyName = $latitudePropertyName;
		$this->longitudePropertyName = $longitudePropertyName;
	}

	/**
	 * @param float $latitude
	 * @param float $longitude
	 * @param int | string $distanceFactor Can be either "km", "mi", 6371 or 3959.
	 * @param string $distanceAs
	 * @return CoreQueryBuilder
	 */
	public function getDistanceQuery(float $latitude, float $longitude, $distanceFactor, string $distanceAs = 'distance'): CoreQueryBuilder
	{
		$cleanedDistanceFactor = $this->cleanDistanceFactor($distanceFactor);

		$query = $this->getConnectionPool()->getQueryBuilderForTable($this->tableName);
		$query
			->select(sprintf('%s.*', $this->tableName))
			->from($this->tableName)
			->orderBy($distanceAs);
		QueryBuilder::addUnquotedSelect($query, $this->getDistanceQueryAttribute($latitude, $longitude, $cleanedDistanceFactor, $distanceAs));
		return $query;
	}

	protected function getDistanceQueryAttribute(float $latitude, float $longitude, int $distanceFactor, string $distanceAs): string
	{
		$antipodalLatitude = -1 * $latitude;
		$antipodalLongitude = $longitude + 180;

		$replace = [
			'{center.latitude}' => (float)$latitude,
			'{center.longitude}' => (float)$longitude,
			'{antipode.latitude}' => $antipodalLatitude,
			'{antipode.longitude}' => $antipodalLongitude,
			'{marker.zerozero}' => '(NOT {marker.latitude} AND NOT {marker.longitude})',
			'{marker.latitude}' => $this->latitudePropertyName,
			'{marker.longitude}' => $this->longitudePropertyName,
			'{distanceFactor}' => $distanceFactor,
			'{result.calculatedDistance}' => $distanceAs
		];

		return str_replace(array_keys($replace), array_values($replace), self::QUERY_TEMPLATE);
	}

	protected function cleanDistanceFactor($distanceFactor): int
	{
		if (MathUtility::canBeInterpretedAsInteger($distanceFactor)) {
			return (int)$distanceFactor;
		} elseif (strtolower($distanceFactor) === 'km') {
			return self::DISTANCE_FACTOR_FOR_KM;
		} elseif (strtolower($distanceFactor) === 'miles' || strtolower($distanceFactor) === 'mi') {
			return self::DISTANCE_FACTOR_FOR_MILES;
		}
	}

	protected function getConnectionPool(): ConnectionPool
	{
		return GeneralUtility::makeInstance(ConnectionPool::class);
	}
}
