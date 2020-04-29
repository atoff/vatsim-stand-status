<?php

namespace CobaltGrid\VatsimStandStatus;

use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToLoadStandDataFileException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToParseStandDataException;
use CobaltGrid\VatsimStandStatus\Libraries\CAACoordinateConverter;
use Vatsimphp\VatsimData;

class StandStatus
{
    /**
     * @var Stand[]
     */
    private $stands = [];
    /**
     * @var Stand[]|null
     */
    private $occupiedStandsCache = null;
    /**
     * @var Stand[]|null
     */
    private $unoccupiedStandsCache = null;
    /**
     * @var Aircraft[]
     */
    private $aircraftSearchResults = [];

    /*
     Supported Coordinate Formats
    */


    const COORD_FORMAT_DECIMAL = 1;
    // Coordinates of format 521756.91N
    const COORD_FORMAT_CAA = 2;

    /*
     Airport Details
    */
    /**
     * @var float
     */
    private $airportLatitude;
    /**
     * @var float
     */
    private $airportLongitude;
    private $airportStandsFile;

    /*
      Configuration Defaults
     */
    private $maxStandDistance = 0.07; // In kilometers
    private $hideStandSidesWhenOccupied = true;
    private $maxDistanceFromAirport = 2; // In kilometers
    private $maxAircraftAltitude = 3000; // In feet
    private $maxAircraftGroundspeed = 10; // In knots

    private $standExtensions = ["L", "C", "R", "A", "B", "N", "E", "S", "W"]; // Possible stand extensions/combinations. E.G Stand 25 includes 25L and 25R
    private $standExtensionPattern = '<standroot><extensions>'; // Use <extensions> to determine where to insert the extensions, and <standroot> to represent the stand number. Can only use one of each
    private $standCoordinateFormat = self::COORD_FORMAT_DECIMAL; // Stand Data file coordinate type


    /**
     * StandStatus constructor.
     * @param string $standDataPath The absolute path to the stand data CSV file
     * @param float $airportLatitude The decimal-format latitude of the airport
     * @param float $airportLongitude The decimal-format longitude of the airport
     * @param int|float $maxAirportDistance The maximum distance, in kilometers, to consider aircraft at the airport
     * @param int $standCoordinateFormat The format of the coordinates in the stand data file. Defaults to decimal.
     * @param bool $parseData Whether to parse the data file automatically after construction
     * @throws CoordinateOutOfBoundsException
     * @throws Exceptions\InvalidStandException
     * @throws UnableToLoadStandDataFileException
     * @throws UnableToParseStandDataException
     */
    public function __construct($standDataPath, $airportLatitude, $airportLongitude, $maxAirportDistance = null, $standCoordinateFormat = self::COORD_FORMAT_DECIMAL, $parseData = true)
    {
        $this->airportStandsFile = $standDataPath;
        $this->airportLatitude = $airportLatitude;
        $this->airportLongitude = $airportLongitude;
        if ($standCoordinateFormat) $this->standCoordinateFormat = $standCoordinateFormat;
        $this->validateCoordinatePairOrFail($airportLatitude, $airportLongitude);

        if ($maxAirportDistance) $this->maxDistanceFromAirport = $maxAirportDistance;

        // Load stand data into memory and parse if allowed
        if ($this->loadStandData() && $parseData) {
            $this->parseData();
        }
    }

    /**
     * Fetches VATSIM pilot data, and runs stand assignment algorithm
     *
     * @return $this
     */
    public function parseData()
    {
        $this->occupiedStandsCache = null;
        $pilots = $this->getVATSIMPilots();
        if ($pilots && $this->getAircraftWithinParameters($pilots)) {
            $this->checkIfAircraftAreOnStand();
        }
        return $this;
    }

    /*
     * Useful functions
     */

    public function allStands()
    {
        return $this->stands;
    }

    public function occupiedStands()
    {
        if ($this->occupiedStandsCache) return $this->occupiedStandsCache;

        return $this->occupiedStandsCache = array_filter($this->stands, function (Stand $stand) {
            return $stand->isOccupied();
        });
    }

    public function unoccupiedStands()
    {
        if ($this->unoccupiedStandsCache) return $this->unoccupiedStandsCache;

        return $this->unoccupiedStandsCache = array_filter($this->stands, function (Stand $stand) {
            return !$stand->isOccupied();
        });
    }

    /*
     * Internal Processing Function
     */

    /**
     * Loads and parse's stand data from the stand data file
     * @return bool
     * @throws UnableToParseStandDataException
     * @throws CoordinateOutOfBoundsException|Exceptions\InvalidStandException|UnableToLoadStandDataFileException
     */
    private function loadStandData()
    {
        $standDataStream = @fopen($this->airportStandsFile, "r");

        if (!$standDataStream) {
            throw new UnableToLoadStandDataFileException("Unable to load the stand data file located at path '{$this->airportStandsFile}'");
        }

        while (($row = fgetcsv($standDataStream, 4096)) !== false) {
            // Assume file data structure of id, latitude, longitude
            $name = $row[0];
            $latitude = $row[1];
            $longitude = $row[2];

            // Check if this is a header row
            if (ctype_alpha($latitude)) {
                continue;
            }

            switch ($this->standCoordinateFormat) {
                case self::COORD_FORMAT_CAA:
                    $converter = new CAACoordinateConverter($latitude, $longitude);
                    $latitude = $converter->latitudeToDecimal();
                    $longitude = $converter->longitudeToDecimal();
                    break;
            }

            $this->validateCoordinatePairOrFail($latitude, $longitude);
            $stand = new Stand($name, $latitude, $longitude, $this->standExtensions, $this->standExtensionPattern);

            if (isset($this->stands[$stand->getKey()])) {
                throw new UnableToParseStandDataException("A stand ID was defined twice in the data file! Stand ID: {$stand->getKey()}");
            }
            $this->stands[$stand->getKey()] = $stand;
        }

        fclose($standDataStream);
        return true;
    }

    /**
     * Returns an array of pilots from the VATSIM data feed
     *
     * @return array
     */
    public function getVATSIMPilots()
    {
        $vatsimData = new VatsimData();

        if (!$vatsimData->loadData()) {
            // VATSIM data file is down.
            return null;
        }

        return $vatsimData->getPilots()->toArray();
    }

    /**
     * Filters network pilot data for aircraft meeting ground conditions
     *
     * @param array $pilots
     * @return bool
     */
    private function getAircraftWithinParameters(array $pilots)
    {
        if (count($pilots) == 0 || (($this->airportLatitude == null) || ($this->airportLongitude == null))) {
            return false;
        }

        $filteredAircraft = [];
        foreach ($pilots as $pilot) {
            $aircraft = new Aircraft($pilot);

            $insideAirfieldRange = $this->distanceBetweenCoordinates($aircraft->latitude, $aircraft->longitude, $this->airportLatitude, $this->airportLongitude)
                < $this->maxDistanceFromAirport;
            $belowSpecifiedGroundspeed = $aircraft->groundspeed <= $this->maxAircraftGroundspeed;
            $belowSpecifiedAltitude = $aircraft->altitude <= $this->maxAircraftAltitude;

            if ($insideAirfieldRange && $belowSpecifiedGroundspeed && $belowSpecifiedAltitude) {
                $filteredAircraft[] = $aircraft;
            }

        }
        $this->aircraftSearchResults = $filteredAircraft;
        return true;
    }

    /**
     * Runs stand assignment algorithm
     *
     * @return void
     */
    private function checkIfAircraftAreOnStand()
    {
        foreach ($this->aircraftSearchResults as $aircraft) {
            // Best stand match
            $standMatch = null;
            // Check each stand to see how close they are
            foreach ($this->stands as $standIndex => $stand) {

                // Find distance between aircraft and stand
                $distance = $this->distanceBetweenCoordinates($stand->latitude, $stand->longitude, $aircraft->latitude, $aircraft->longitude);

                $distanceInsideBound = $distance < $this->maxStandDistance;

                if ($distanceInsideBound && (!$standMatch || $distance < $standMatch['distance'])) {
                    // Best match at the moment
                    $standMatch = [
                        'index' => $standIndex,
                        'stand' => $stand,
                        'distance' => $distance,
                    ];
                }
            }

            // If we have a match, set it as occupied
            if ($standMatch) {
                $this->setStandGroupOccupied($standMatch['stand'], $aircraft);
            }
        }
    }


    /**
     * Sets the given stand (by index reference) to occupied
     *
     * @param Stand $stand
     * @param Aircraft $aircraft
     */
    private function setStandOccupied(Stand $stand, Aircraft $aircraft)
    {
        $this->stands[$stand->getKey()]->setOccupier($aircraft);
    }

    /**
     * Sets a stand and its complementing stands as occupied
     *
     * @param Stand $stand
     * @param Aircraft $aircraft
     */
    private function setStandGroupOccupied(Stand $stand, Aircraft $aircraft)
    {
        // Firstly set the actual stand as occupied
        $this->setStandOccupied($stand, $aircraft);
        $aircraft->setStandIndex($stand->getKey());

        // Get complementary stands
        $standSides = $this->complementaryStands($stand);
        if ($standSides) {

            foreach ($standSides as $stand) {
                if ($this->hideStandSidesWhenOccupied) {
                    unset($this->stands[$stand->getKey()]);
                    continue;
                }

                $this->setStandOccupied($stand, $aircraft);
            }
        }
    }

    /**
     * Generates possible matching side stands for a given stand
     *
     * @param Stand $stand
     * @return array|null
     */
    private function complementaryStands(Stand $stand)
    {
        $root = $stand->getRoot();
        $stands = [];

        foreach (array_merge([''], $this->standExtensions) as $extension) {
            // Generate expected stand name
            $standName = str_replace(['<standroot>', '<extensions>'], [$root, $extension], $this->standExtensionPattern);
            if ($standName != $stand->getName() && isset($this->stands[$standName])) $stands[] = $this->stands[$standName];
        }

        return count($stands) > 0 ? $stands : null;
    }


    /*
     * Helpers
     */


    /**
     * Validates a given latitude/longitude pair and throws an exception if invalid
     *
     * @param $latitude
     * @param $longitude
     * @return bool
     * @throws CoordinateOutOfBoundsException
     */
    private function validateCoordinatePairOrFail($latitude, $longitude)
    {
        if ($this->validateLatitudeCoordinate($latitude) && $this->validateLongitudeCoordinate($longitude)) {
            return true;
        }
        throw new CoordinateOutOfBoundsException;
    }

    /**
     * Validates a given latitude coordinate to make sure it is realistic
     *
     * @param float $coordinate
     * @return bool
     */
    private function validateLatitudeCoordinate($coordinate)
    {
        return $coordinate <= 90 && $coordinate >= -90;
    }

    /**
     * Validates a given longitude coordinate to make sure it is realistic
     *
     * @param float $coordinate
     * @return bool
     */
    private function validateLongitudeCoordinate($coordinate)
    {
        return $coordinate <= 180 && $coordinate >= -180;
    }

    /**
     * Return the distance in kilometres between two sets of coordinates
     * @param float $latitude1 Latitude in decimal format
     * @param float $longitude1 Longitude in decimal format
     * @param float $latitude2 Latitude in decimal format
     * @param float $longitude2 Longitude in decimal format
     * @return float|int
     */
    private function distanceBetweenCoordinates($latitude1, $longitude1, $latitude2, $longitude2)
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


    /*
     * Getters and Setters
     */


    /**
     * @return float
     */
    public function getMaxStandDistance()
    {
        return $this->maxStandDistance;
    }

    /**
     * @param float|int $distance
     * @return $this
     */
    public function setMaxStandDistance($distance)
    {
        $this->maxStandDistance = $distance;
        return $this;
    }

    /**
     * @return bool
     */
    public function getHideStandSidesWhenOccupied()
    {
        return $this->hideStandSidesWhenOccupied;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setHideStandSidesWhenOccupied($bool)
    {
        $this->hideStandSidesWhenOccupied = $bool;
        return $this;
    }

    /**
     * @return float|int
     */
    public function getMaxDistanceFromAirport()
    {
        return $this->maxDistanceFromAirport;
    }

    /**
     * @param float|int $distance
     * @return $this
     */
    public function setMaxDistanceFromAirport($distance)
    {
        $this->maxDistanceFromAirport = $distance;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAircraftAltitude()
    {
        return $this->maxAircraftAltitude;
    }

    /**
     * @param int $altitude
     * @return $this
     */
    public function setMaxAircraftAltitude($altitude)
    {
        $this->maxAircraftAltitude = $altitude;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAircraftGroundspeed()
    {
        return $this->maxAircraftGroundspeed;
    }

    /**
     * @param int $speed
     * @return $this
     */
    public function setMaxAircraftGroundspeed($speed)
    {
        $this->maxAircraftGroundspeed = $speed;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getStandExtensions()
    {
        return $this->standExtensions;
    }

    /**
     * @param string[] $standArray
     * @return $this
     */
    public function setStandExtensions($standArray)
    {
        $this->standExtensions = $standArray;
        return $this;
    }

    /**
     * @return Aircraft[]
     */
    public function getAllAircraft()
    {
        return $this->aircraftSearchResults;
    }
}

