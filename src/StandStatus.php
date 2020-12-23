<?php

namespace CobaltGrid\VatsimStandStatus;

use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidCoordinateFormat;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidICAOCodeException;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidStandException;
use CobaltGrid\VatsimStandStatus\Exceptions\NoStandDataException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToLoadStandDataFileException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToParseStandDataException;
use CobaltGrid\VatsimStandStatus\Libraries\CAACoordinateConverter;
use CobaltGrid\VatsimStandStatus\Libraries\DecimalCoordinateHelper;
use CobaltGrid\VatsimStandStatus\Libraries\OSMStandData;
use Throwable;
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


    // Coordinates of format 51.12345
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

    /*
      Configuration Defaults
     */
    private $maxStandDistance = 0.07; // In kilometers
    private $hideStandSidesWhenOccupied = true;
    private $maxDistanceFromAirport = 2; // In kilometers
    private $maxAircraftAltitude = 3000; // In feet
    private $maxAircraftGroundspeed = 10; // In knots

    private $standExtensions = ['L', 'C', 'R', 'A', 'B', 'N', 'E', 'S', 'W']; // Possible stand extensions/combinations. E.G Stand 25 includes 25L and 25R
    private $standExtensionPattern = '<standroot><extensions>'; // Use <extensions> to determine where to insert the extensions, and <standroot> to represent the stand number. Can only use one of each
    private $standCoordinateFormat = self::COORD_FORMAT_DECIMAL; // Stand Data file coordinate type


    /**
     * StandStatus constructor.
     * @param float $airportLatitude The decimal-format latitude of the airport
     * @param float $airportLongitude The decimal-format longitude of the airport
     * @param int $standCoordinateFormat The format of the coordinates in the stand data file. Defaults to decimal.
     * @throws CoordinateOutOfBoundsException
     */
    public function __construct(
        $airportLatitude,
        $airportLongitude,
        $standCoordinateFormat = self::COORD_FORMAT_DECIMAL
    ) {
        $this->airportLatitude = $airportLatitude;
        $this->airportLongitude = $airportLongitude;
        if ($standCoordinateFormat) $this->standCoordinateFormat = $standCoordinateFormat;
        DecimalCoordinateHelper::validateCoordinatePairOrFail($airportLatitude, $airportLongitude);
    }

    /**
     * Loads and parse's stand data from the stand data csv file
     *
     * @param string $filePath Path to the Stand Data CSV file
     * @return StandStatus
     * @throws CoordinateOutOfBoundsException
     * @throws Exceptions\InvalidStandException
     * @throws UnableToLoadStandDataFileException
     * @throws UnableToParseStandDataException
     */
    public function loadStandDataFromCSV(string $filePath)
    {
        $this->stands = [];

        try {
            $standDataStream = fopen($filePath, "r");
        } catch (Throwable $e) {
            throw new UnableToLoadStandDataFileException("Unable to load the stand data file located at path '{$filePath}' ({$e->getMessage()})");
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

            DecimalCoordinateHelper::validateCoordinatePairOrFail($latitude, $longitude);
            $this->addStand($name, $latitude, $longitude);
        }
        fclose($standDataStream);
        return $this;
    }

    /**
     * Fetches parking position data from the OpenStreetMap's Overpass API.
     *
     * It should be noted that OSM will not always have all the data needed for every airport, and some of the data may
     * be incorrect, out of date or ill-formatted. Check on the OpenStreetMap's editor to ensure your airport has coverage.
     *
     * OpenStreetMap data is licensed under the ODbL license. If you use this method, you MUST provide appropriate
     * attribution to OSM. See https://www.openstreetmap.org/copyright for details.
     *
     * @param string $icao 4 letter ICAO code for the airport
     * @param OSMStandData $osmDataLibrary Optional. Inject the OSMStandData library if you would like to change the default settings
     * @return StandStatus
     * @throws CoordinateOutOfBoundsException|InvalidStandException|InvalidStandException|InvalidCoordinateFormat|UnableToLoadStandDataFileException|UnableToParseStandDataException|InvalidICAOCodeException
     */
    public function fetchAndLoadStandDataFromOSM($icao, OSMStandData $osmDataLibrary = null)
    {
        if ($this->standCoordinateFormat != self::COORD_FORMAT_DECIMAL) {
            throw new InvalidCoordinateFormat('To use OSM map data, the stand coordinate format must be set to decimal!');
        }

        if (!$osmDataLibrary) $osmDataLibrary = new OSMStandData($icao);
        $csvPath = $osmDataLibrary->fetchStandData($this->airportLatitude, $this->airportLongitude, $this->maxDistanceFromAirport * 3);
        return $this->loadStandDataFromCSV($csvPath);
    }

    /**
     * Loads in stand data from an array.
     *
     * @param array $standData Array of stands. Expects each stand to have the name/id at index 0, latitude at index 1, and longitude at index 2
     * @return StandStatus
     * @throws InvalidStandException|UnableToParseStandDataException
     */
    public function loadStandDataFromArray(array $standData)
    {
        $this->stands = [];

        foreach ($standData as $stand) {
            $this->addStand($stand[0], $stand[1], $stand[2]);
        }
        return $this;
    }

    /**
     * Fetches VATSIM pilot data, and runs stand assignment algorithm
     *
     * @return StandStatus
     * @throws NoStandDataException
     */
    public function parseData()
    {
        // If no stands loaded, throw
        if (count($this->stands) == 0) {
            throw new NoStandDataException();
        }

        // Flush any stand caches
        $this->occupiedStandsCache = null;
        $this->unoccupiedStandsCache = null;

        // Fetch pilot data
        $vatsimData = new VatsimData();
        $pilots = $this->getVATSIMPilots($vatsimData);

        // Clear existing matches
        foreach ($this->stands as &$stand) {
            $stand->clearParsedData();
        }

        // If we have pilots, filter and run assignment program
        if ($pilots && $this->getAircraftWithinParameters($pilots)) {
            $this->checkIfAircraftAreOnStand();
        }
        return $this;
    }

    /*
     * Useful functions
     */

    /**
     * Returns a list of all the stands (minus hidden side stands if enabled)
     *
     * @param bool $assoc
     * @return Stand[]
     */
    public function stands($assoc = false)
    {
        if (!$this->hideStandSidesWhenOccupied) {
            return $this->allStands($assoc);
        }
        return array_filter($this->allStands($assoc), function (Stand $stand) {
            return !$stand->isPartOfOccupiedGroup();
        });
    }

    /**
     * Returns all loaded stands, irrespective of if they are hidden or not
     *
     * @param bool $assoc
     * @return Stand[]
     */
    public function allStands($assoc = false)
    {
        return $assoc ? $this->stands : array_values($this->stands);
    }

    /**
     * Returns a list of all occupied stands
     *
     * @param bool $assoc If true, the indexes of the array will be equal to the stand name/id. Default false
     * @return Stand[]
     */
    public function occupiedStands($assoc = false)
    {
        if (!$this->occupiedStandsCache) {
            $this->occupiedStandsCache = array_filter($this->stands(true), function (Stand $stand) {
                return $stand->isOccupied();
            });
        }

        return $assoc ? $this->occupiedStandsCache : array_values($this->occupiedStandsCache);
    }

    /**
     * Returns a list of all unoccupied stands
     *
     * @param bool $assoc If true, the indexes of the array will be equal to the stand name/id. Default false
     * @return Stand[]
     */
    public function unoccupiedStands($assoc = false)
    {
        if (!$this->unoccupiedStandsCache) {
            $this->unoccupiedStandsCache = array_filter($this->stands(true), function (Stand $stand) {
                return !$stand->isOccupied();
            });
        }

        return $assoc ? $this->unoccupiedStandsCache : array_values($this->unoccupiedStandsCache);
    }

    /**
     * Returns all the aircraft that are deemed on the ground
     *
     * @return Aircraft[]
     */
    public function allAircraft()
    {
        return $this->aircraftSearchResults;
    }

    /*
     * Internal Processing Function
     */


    /**
     * Returns an array of pilots from the VATSIM data feed
     *
     * @param VatsimData $vatsimData
     * @return array
     */
    public function getVATSIMPilots(VatsimData $vatsimData)
    {
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
        $filteredAircraft = [];
        foreach ($pilots as $pilot) {
            $aircraft = new Aircraft($pilot);

            $insideAirfieldRange = DecimalCoordinateHelper::distanceBetweenCoordinates($aircraft->latitude, $aircraft->longitude, $this->airportLatitude, $this->airportLongitude)
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
                $distance = DecimalCoordinateHelper::distanceBetweenCoordinates($stand->latitude, $stand->longitude, $aircraft->latitude, $aircraft->longitude);

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
     * Adds a stand to the lift of stands
     *
     * @param $id
     * @param $latitude
     * @param $longitude
     * @throws Exceptions\InvalidStandException
     * @throws UnableToParseStandDataException
     */
    private function addStand($id, $latitude, $longitude)
    {
        $stand = new Stand($id, $latitude, $longitude, $this->standExtensions, $this->standExtensionPattern);

        if (isset($this->stands[$stand->getKey()])) {
            throw new UnableToParseStandDataException("A stand ID was defined twice in the stand data! Stand ID: {$stand->getKey()}");
        }
        $this->stands[$stand->getKey()] = $stand;
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
     * @return string
     */
    public function getStandExtensionPattern()
    {
        return $this->standExtensionPattern;
    }

    /**
     * @param string $standExtensionPattern
     * @return StandStatus
     */
    public function setStandExtensionPattern(string $standExtensionPattern)
    {
        $this->standExtensionPattern = $standExtensionPattern;
        return $this;
    }
}
