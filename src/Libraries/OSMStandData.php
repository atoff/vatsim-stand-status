<?php

namespace CobaltGrid\VatsimStandStatus\Libraries;

use CobaltGrid\VatsimStandStatus\Exceptions\InvalidICAOCodeException;
use GuzzleHttp\Client;

/*
 * Note: OpenStreetMap Data is licensed under the Open Data Commons Open Database License (ODbl).
 * Use of the data downloaded from the Overpass API must be credited to OpenStreetMap Contributors.
 * If you are using OpenStreetMap for tiles, you might already be doing this if you use the data as part of a map.
 * Visit https://www.openstreetmap.org/copyright to find out more.
 */

class OSMStandData
{

    private $overpassAPIUrl = "https://overpass-api.de/api/interpreter?data=";
    private $cacheFolder;

    // 3 months
    private $cacheTTL = 60 * 60 * 24 * 30 * 3;
    private $timeout = 25;

    private $airportICAO;

    private $client;

    /**
     * OSMStandData constructor.
     *
     * @param string $icao 4 letter ICAO code for airport
     * @param Client|null $client Optional Guzzle Client Injection
     * @throws InvalidICAOCodeException
     */
    public function __construct(string $icao, Client $client = null)
    {
        // Validate ICAO Code
        $icao = strtoupper($icao);
        if (strlen($icao) !== 4 || !ctype_alpha($icao)) {
            throw new InvalidICAOCodeException("{$icao} is not a valid ICAO code");
        }
        $this->airportICAO = $icao;
        $this->cacheFolder = dirname(__FILE__) . '/../../storage/data';
        if ($client) $this->client = $client; else $this->client = new Client();
    }

    /**
     * Fetches stand data from the Overpass API.
     *
     * If no results found, this function will return null.
     * Otherwise, will return the absolute path to the created CSV.
     *
     * @param float $centerLatitude The defined airport center latitude
     * @param float $centerLongitude The defined airport center longitude
     * @param int $radius The radius in km from which to create the bounding box for the search
     * @return string|null
     */
    public function fetchStandData($centerLatitude, $centerLongitude, $radius = 6)
    {
        if ($this->cachedCSVPath()) {
            return $this->cachedCSVPath();
        }

        // Download data from OSM API
        $result = $this->client->get($this->overpassAPIUrl . $this->composeQuery($centerLatitude, $centerLongitude, $radius));
        $body = $result->getBody()->getContents();

        // Parse the rows
        $csv = str_getcsv($body, "\n");
        $stands = [];
        // Check and remove duplicates
        foreach ($csv as $index => $row) {
            // Handle header row
            if ($index == 0) {
                $csv[$index] = "id,latitude,longitude,This data was extracted from the OpenStreetMap API and is licensed under the ODbL license. It's use must be attributed";
                continue;
            }
            $row = str_getcsv($row);

            // name tag from data - fallback name for stand id
            $standName = $row[1];
            $featureType = $row[2];
            // Ref tag from data - primarily used as the stand id
            $standRef = $row[0] ?? $standName;

            // Check if stand has already been processed (i.e. this is a duplicate)
            if (($key = array_search($standRef, $stands)) !== false) {

                // If the stand name tag is null, is same as the ref tag, or is already in the stand array
                if (!$standName || $standName == $standRef || in_array($standName, $stands)) {
                    // Allow nodes to take preference
                    if ($featureType == "node") {
                        // Remove the already registered stand from the registered list, and the CSV
                        unset($stands[$key]);
                        unset($csv[$key]);
                    } else {
                        // Remove this stand from the CSV
                        unset($csv[$index]);
                        continue;
                    }
                }else{
                    // We can use the stand name instead
                    $standRef = $standName;
                }
            }

            $csv[$index] = implode(',', [$standRef, $row[3], $row[4]]);
            $stands[$index] = $standRef;
        }

        $csv = implode("\n", $csv);

        // Store file
        file_put_contents($this->getCacheFilePath(), $csv, LOCK_EX);
        return $this->cachedCSVPath();
    }

    /**
     * Deletes the cached file (if exists)
     *
     * @return bool Returns true if deleted, or false if not deleted / error.
     */
    public function deleteCachedData()
    {
        return file_exists($this->getCacheFilePath()) ? unlink($this->getCacheFilePath()) : false;
    }

    /**
     * Composes the Overpass Query Language Query
     *
     * @param $centerLatitude
     * @param $centerLongitude
     * @param $radius
     * @return string URL Encoded Query
     */
    private function composeQuery($centerLatitude, $centerLongitude, $radius)
    {
        $diagonalRadius = $radius / cos(deg2rad(45));

        // Set a hard limit for radius
        if ($diagonalRadius > 20) {
            $diagonalRadius = 20;
        }

        // Generate bounding box following South, West, North, East format
        $bottomLeft = DecimalCoordinateHelper::distanceFromCoordinate($centerLatitude, $centerLongitude, $diagonalRadius, 180 + 45);
        $topRight = DecimalCoordinateHelper::distanceFromCoordinate($centerLatitude, $centerLongitude, $diagonalRadius, 45);

        return urlencode("
            [bbox:{$bottomLeft->latitude},{$bottomLeft->longitude},{$topRight->latitude},{$topRight->longitude}]
            [out:csv(ref,name,::type,::lat,::lon;true;\",\")][timeout:{$this->timeout}];
            ( 
              nwr[aeroway=aerodrome][icao=\"{$this->airportICAO}\"];
            );
            map_to_area;
            nwr[aeroway=parking_position][~\"^name|ref?$\"~\".\"](area);
            out tags center;
        ");
    }

    /**
     * Returns either the path to the cached CSV file, or null in the case of no / expired cache.
     *
     * @return string|null
     */
    private function cachedCSVPath()
    {
        $fileExists = file_exists($this->getCacheFilePath());
        $fileUpdatedWithinCacheTTL = $fileExists && filemtime($this->getCacheFilePath()) > (time() - $this->cacheTTL);

        return $fileExists && $fileUpdatedWithinCacheTTL ? $this->getCacheFilePath() : null;
    }

    /**
     * Returns the path to the stand stand CSV file, assuming it exists
     *
     * @return string
     */
    public function getCacheFilePath()
    {
        return $this->cacheFolder . "/OSM-{$this->airportICAO}-stand-data.csv";
    }

    /**
     * Set the length of time to cache stand data files
     *
     * @param int $cacheTTL Time in seconds
     * @return OSMStandData
     */
    public function setCacheTTL($cacheTTL)
    {
        $this->cacheTTL = $cacheTTL;
        return $this;
    }

    /**
     * Set the timeout length for retrieving data from Overpass API. If you are expecting a large data return,
     * you could increase this to prevent timeouts.
     *
     * @param int $timeout
     * @return OSMStandData
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

}