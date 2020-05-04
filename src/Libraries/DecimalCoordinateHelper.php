<?php


namespace CobaltGrid\VatsimStandStatus\Libraries;


use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;

class DecimalCoordinateHelper
{
    /**
     * Validates a given latitude/longitude pair and throws an exception if invalid
     *
     * @param $latitude
     * @param $longitude
     * @return bool
     * @throws CoordinateOutOfBoundsException
     */
    public static function validateCoordinatePairOrFail($latitude, $longitude)
    {
        if (self::validateLatitudeCoordinate($latitude) && self::validateLongitudeCoordinate($longitude)) {
            return true;
        }
        throw new CoordinateOutOfBoundsException;
    }

    /**
     * Validates a given latitude coordinate to make sure it is realistic
     *
     * @param float $coordinate Coordinate is decimal format
     * @return bool
     */
    public static function validateLatitudeCoordinate($coordinate)
    {
        return $coordinate <= 90 && $coordinate >= -90;
    }

    /**
     * Validates a given longitude coordinate to make sure it is realistic
     *
     * @param float $coordinate Coordinate is decimal format
     * @return bool
     */
    public static function validateLongitudeCoordinate($coordinate)
    {
        return $coordinate <= 180 && $coordinate >= -180;
    }

    /**
     * Return the distance in kilometres between two sets of coordinates using the haversine formula
     *
     * @param float $latitude1 Latitude in decimal format
     * @param float $longitude1 Longitude in decimal format
     * @param float $latitude2 Latitude in decimal format
     * @param float $longitude2 Longitude in decimal format
     * @return float|int
     */
    public static function distanceBetweenCoordinates($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $earth_radius = 6371;

        $latitude1 = floatval($latitude1);
        $longitude1 = floatval($longitude1);
        $latitude2 = floatval($latitude2);
        $longitude2 = floatval($longitude2);

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * asin(sqrt($a));
        return $earth_radius * $c;
    }

    /**
     * Return the distance in kilometres between two sets of coordinates using the haversine formula
     *
     * @param float $latitude Latitude in decimal format
     * @param float $longitude Longitude in decimal format
     * @param float|int $distance Distance in km
     * @param int $direction Clockwise direction in degrees, where north is 0 degrees, east is 90 degrees, etc.
     * @return object Object with properties `latitude` and `longitude` accessible
     */
    public static function distanceFromCoordinate($latitude, $longitude, $distance, $direction)
    {
        $earth_radius = 6371;

        $latitude = floatval($latitude) * pi()/180;
        $longitude = floatval($longitude) * pi()/180;
        $delta = $distance/$earth_radius;
        $directionRad = $direction * pi()/180;

        $newLatitudeRad = asin(sin($latitude)*cos($delta) + cos($latitude)*sin($delta)*cos($directionRad));
        $newLongitudeRad = $longitude + atan2(sin($directionRad)*sin($delta)*cos($latitude), cos($delta) - sin($latitude)*sin($newLatitudeRad));

        return (object) ['latitude' => $newLatitudeRad * 180/pi(), 'longitude' => $newLongitudeRad * 180/pi()];
    }
}