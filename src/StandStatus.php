<?php
    namespace CobaltGrid\VatsimStandStatus;

    use Vatsimphp\VatsimData;

    class StandStatus
    {

        public $stands = array();
        public $occupiedStands = array();
        public $aircraftSearchResults;

        /*
         Airport Stand Details
        */

        public $airportICAO;
        public $airportName;
        public $airportCoordinates;

        public $airportStandsFile;

        /*
          Configuration
         */

        private $maxStandDistance = 0.07; // In kilometeres
        private $hideStandSidesWhenOccupied = true;
        private $maxDistanceFromAirport = 2; // In kilometeres
        private $maxAircraftAltitude = 3000; // In feet
        private $maxAircraftGroundspeed = 10; // In knots
        private $standExtensions = array("L", "C", "R", "A", "B");


        public function __construct($airportICAO, $airportStandsFile, $airportLatCoordinate, $airportLongCoordinate, $parseData = true, $maxAirportDistance = null)
        {
            $this->airportICAO = $airportICAO;
            $this->airportStandsFile = $airportStandsFile;
            $this->airportCoordinates = array("lat" => $airportLatCoordinate, "long" => $airportLongCoordinate);
            if ($maxAirportDistance != null) {
                $this->maxDistanceFromAirport = $maxAirportDistance;
            }

            if ($this->loadStandsData()) {
                if ($parseData) {
                    $this->parseData();
                }
            }

        }

        public function allStands($pageNo = null, $pageLimit = null)
        {
            if ($pageLimit == null) {
                return $this->stands;
            } else {
                if ($pageNo == null) {
                    // Assume first page
                    return array_slice($this->stands, 0, $pageLimit);
                } else {
                    return array_slice($this->stands, ($pageNo * $pageLimit) - $pageLimit, $pageLimit);
                }

            }

        }

        public function occupiedStands($pageNo = null, $pageLimit = null)
        {
            $occupiedStands = $this->occupiedStands;
            foreach ($occupiedStands as $stand) {
                $occupiedStands[$stand] = $this->stands[$stand]; // Fill in pilot data
            }

            if ($pageLimit == null) {
                return $occupiedStands;
            } else {
                if ($pageNo == null) {
                    // Assume first page
                    return array_slice($occupiedStands, 0, $pageLimit);
                } else {
                    return array_slice($occupiedStands, ($pageNo * $pageLimit) - $pageLimit, $pageLimit);
                }

            }
        }

        public function allStandsPaginationArray($pageLimit)
        {
            // Work out the ammount of pages
            $noOfPages = ceil(count($this->stands) / $pageLimit);
            $pageinationArray = array();
            for ($i = 0; $i < $noOfPages; $i++) {
                $pageinationArray[] = $this->allStands($i, $pageLimit);
            }

            return $pageinationArray;


        }

        function parseData()
        {
            if ($this->getAircraftWithinParameters()) {
                $this->checkIfAircraftAreOnStand();
            }
			return $this;
        }

        // Load the stand data
        function loadStandsData()
        {
            $array = $fields = array();
            $i = 0;
            $handle = @fopen($this->airportStandsFile, "r");
            if ($handle) {
                while (($row = fgetcsv($handle, 4096)) !== false) {
                    if (empty($fields)) {
                        $fields = $row;
                        continue;
                    }
                    $y = 0;
                    foreach ($row as $k => $value) {
                        if ($y == 1) { // Convert LAT coordinate
                            $array[$row[0]][$fields[$k]] = $this->convertCAALatCoord($value);
                        } else if ($y == 2) { // Convert LONG coordinate
                            $array[$row[0]][$fields[$k]] = $this->convertCAALongCoord($value);
                        } else {
                            $array[$row[0]][$fields[$k]] = $value;
                        }
                        $y++;
                    }
                    $i++;
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                    return false;
                }
                fclose($handle);
            } else {
                return false;
            }
            $this->stands = $array;
            return true;
        }

        function getAircraftWithinParameters()
        {
            $vatsim = new VatsimData();
            $vatsim->loadData();

            $pilots = $vatsim->getPilots()->toArray();

            // INSERT TEST PILOTS
            //$pilots[] = array('callsign' => "TEST", "latitude" => 55.949228, "longitude" => -3.364303, "altitude" => 0, "groundspeed" => 0, "planned_destairport" => "TEST", "planned_depairport" => "TEST");

            if (count($pilots) == 0) {
                return false;
            }
            if (($this->airportCoordinates['lat'] == null) || ($this->airportCoordinates['long'] == null)) {
                return false;
            }


            $filteredResults = array();
            foreach ($pilots as $pilot) {
                if (($this->getCoordDistance($pilot['latitude'], $pilot['longitude'], $this->airportCoordinates['lat'], $this->airportCoordinates['long']) < $this->maxDistanceFromAirport)) {
                    if (($pilot['groundspeed'] <= $this->maxAircraftGroundspeed) && ($pilot['altitude'] <= $this->maxAircraftAltitude)) {
                        $filteredResults[] = $pilot;
                    }
                }

            }
            $this->aircraftSearchResults = $filteredResults;
            return true;
        }

        function checkIfAircraftAreOnStand()
        {
            $pilots = $this->aircraftSearchResults;
            $stands = $this->stands;
            $standDistanceBoundary = $this->maxStandDistance;

            foreach ($pilots as $pilot) {

                // Array to hold the stands they could possibly be on
                $possibleStands = array();

                // Check each stand to see how close they are
                foreach ($stands as $stand) {

                    // Find distance between aircraft and stand
                    $distance = $this->getCoordDistance($stand['latcoord'], $stand['longcoord'], $pilot['latitude'], $pilot['longitude']);


                    if ($distance < $standDistanceBoundary) {
                        // This could be a possible stand as the aircraft is close
                        $possibleStands[] = array('id' => $stand['id'], 'distance' => $distance);
                    }

                }

                // Check how many stands are possible
                if (count($possibleStands) > 1) {

                    $minDistance = $standDistanceBoundary; // Cant be more than $standDistanceBoundary
                    $minStandID = null;

                    foreach ($possibleStands as $stand) {

                        if ($stand['distance'] < $minDistance) {
                            // New smallest distance from stand
                            $minDistance = $stand['distance'];
                            $minStandID = $stand['id'];
                        }

                    }
                    $this->checkAndSetStandOccupied($minStandID, $pilot);
                } else if (count($possibleStands) == 1) {
                    $this->checkAndSetStandOccupied($possibleStands[0]['id'], $pilot);
                }

            }
        }

        function checkAndSetStandOccupied($standID, $pilot)
        {

            // Firstly set the acutal stand as occupied
            $this->setStandOccupied($standID, $pilot);

            // Check for side stands
            $standSides = $this->standSides($standID);
            if ($standSides) {

                foreach ($standSides as $stand) {
                    $this->setStandOccupied($stand, $pilot);
                }

                // Hide the side stands when option is set
                if ($this->hideStandSidesWhenOccupied) {

                    // Get the stand root number
                    $standRoot = str_replace("R", "", $standID);
                    $standRoot = str_replace("L", "", $standRoot);
                    $standRoot = str_replace("A", "", $standRoot);
                    $standRoot = str_replace("B", "", $standRoot);
                    $standRoot = str_replace("C", "", $standRoot);

                    if (isset($this->stands[$standRoot])) {
                        // Stand root is an actual stand
                        if (isset($this->stands[$standRoot . "R"])) {
                            $this->unsetStandOccupied($standRoot . "R");
                            unset($this->stands[$standRoot . "R"]);
                        }
                        if (isset($this->stands[$standRoot . "L"])) {
                            $this->unsetStandOccupied($standRoot . "L");
                            unset($this->stands[$standRoot . "L"]);
                        }
                        if (isset($this->stands[$standRoot . "A"])) {
                            $this->unsetStandOccupied($standRoot . "A");
                            unset($this->stands[$standRoot . "A"]);
                        }
                        if (isset($this->stands[$standRoot . "B"])) {
                            $this->unsetStandOccupied($standRoot . "B");
                            unset($this->stands[$standRoot . "B"]);
                        }

                    } else if (isset($this->stands[$standRoot . "C"])) {
                        // Stand Root + C (i.e 551C) is an actual stand
                        if (isset($this->stands[$standRoot . "R"])) {
                            $this->unsetStandOccupied($standRoot . "R");
                            unset($this->stands[$standRoot . "R"]);
                        }
                        if (isset($this->stands[$standRoot . "L"])) {
                            $this->unsetStandOccupied($standRoot . "L");
                            unset($this->stands[$standRoot . "L"]);
                        }
                        if (isset($this->stands[$standRoot . "A"])) {
                            $this->unsetStandOccupied($standRoot . "A");
                            unset($this->stands[$standRoot . "A"]);
                        }
                        if (isset($this->stands[$standRoot . "B"])) {
                            $this->unsetStandOccupied($standRoot . "B");
                            unset($this->stands[$standRoot . "B"]);
                        }
                    }
                }
            }
        }

        function setStandOccupied($standID, $pilot)
        {
            $this->stands[$standID]['occupied'] = $pilot;
            $this->occupiedStands[$standID] = $standID;
        }

        function unsetStandOccupied($standID)
        {
            if (isset($this->stands[$standID]['occupied'])) {
                unset($this->stands[$standID]['occupied']);
            }
            unset($this->occupiedStands[$standID]);
        }

        function standSides($standID)
        {
            $standSides = array();
            $stands = $this->stands;

            //Find the 'base' stand number
            $standBase = str_replace("R", "", $standID);
            $standBase = str_replace("L", "", $standBase);
            $standBase = str_replace("A", "", $standBase);
            $standBase = str_replace("B", "", $standBase);
            $standBase = str_replace("C", "", $standBase);


            // Check if stand has a side already
            if (strstr($standID, "R") || strstr($standID, "L")) {
                // Our stand is already L/R
                if (strstr($standID, "R")) {
                    // Set the right hand side to occupied aswell
                    $newStand = str_replace("R", "L", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the base stand to occupied also
                    $newStand = str_replace("R", "", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the center stand to occupied also
                    $newStand = str_replace("R", "C", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                } else {
                    // Set the left hand side to occupied aswell
                    $newStand = str_replace("L", "R", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the base stand to occupied also
                    $newStand = str_replace("L", "", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the center stand to occupied also
                    $newStand = str_replace("L", "C", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                }
            } else if (strstr($standID, "A") || strstr($standID, "B")) {
                // Our stand already is A / B

                if (strstr($standID, "A")) {
                    // Set the right hand side to occupied aswell
                    $newStand = str_replace("A", "B", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the base stand to occupied also
                    $newStand = str_replace("A", "", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the center stand to occupied also
                    $newStand = str_replace("A", "C", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                } else {
                    // Set the right hand side to occupied aswell
                    $newStand = str_replace("B", "A", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the base stand to occupied also
                    $newStand = str_replace("B", "", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                    // Set the center stand to occupied also
                    $newStand = str_replace("B", "C", $standID);
                    if (isset($stands[$newStand])) {
                        $standSides[] = $newStand;
                    }
                }

            } else {
                // Stand itself has no side, but may have L / R / A / B sides
                if (isset($stands[$standBase . "L"])) {
                    $standSides[] = $standBase . "L";
                }
                if (isset($stands[$standBase . "R"])) {
                    $standSides[] = $standBase . "R";
                }
                if (isset($stands[$standBase . "C"])) {
                    $standSides[] = $standBase . "C";
                }
                if (isset($stands[$standBase . "A"])) {
                    $standSides[] = $standBase . "A";
                }
                if (isset($stands[$standBase . "B"])) {
                    $standSides[] = $standBase . "B";
                }
            }

            if (count($standSides) == 0) {
                return false;
            } else {
                return $standSides;
            }

        }


        /*

          Support Functions

         */

        function getCoordDistance($latitude1, $longitude1, $latitude2, $longitude2)
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
            $d = $earth_radius * $c;

            return $d;

        }

        function convertCoordinateToDecimal($deg, $min, $sec, $dir)
        {
            // Converting DMS ( Degrees / minutes / seconds ) to decimal format
            if ($dir == "W") {
                return "-" . ($deg + ((($min * 60) + ($sec)) / 3600));
            } else if ($dir == "S") {
                return "-" . ($deg + ((($min * 60) + ($sec)) / 3600));
            }
            return $deg + ((($min * 60) + ($sec)) / 3600);
        }

        function convertCAALatCoord($coord)
        {
            $deg = substr($coord, 0, 2);
            $min = substr($coord, 2, 2);
            $sec = substr($coord, 4, 5);
            $dir = substr($coord, -1);
            return $this->convertCoordinateToDecimal($deg, $min, $sec, $dir);
        }

        function convertCAALongCoord($coord)
        {
            $deg = substr($coord, 0, 3);
            $min = substr($coord, 3, 2);
            $sec = substr($coord, 5, 5);
            $dir = substr($coord, -1);
            return $this->convertCoordinateToDecimal($deg, $min, $sec, $dir);
        }

        function getMaxStandDistance()
        {
            return $this->maxStandDistance;
        }


        function setMaxStandDistance($distance)
        {
            $this->maxStandDistance = $distance;
            return $this;
        }

        function getHideStandSidesWhenOccupied()
        {
            return $this->hideStandSidesWhenOccupied;
        }

        function setHideStandSidesWhenOccupied($bool)
        {
            $this->hideStandSidesWhenOccupied = $bool;
            return $this;
        }

        function getMaxDistanceFromAirport()
        {
            return $this->maxDistanceFromAirport;
        }

        function setMaxDistanceFromAirport($distance)
        {
            $this->maxDistanceFromAirport = $distance;
            return $this;
        }

        function getMaxAircraftAltitude()
        {
            return $this->maxAircraftAltitude;
        }

        function setMaxAircraftAltitude($altitude)
        {
            $this->maxAircraftAltitude = $altitude;
            return $this;
        }

        function getMaxAircraftGroundspeed()
        {
            return $this->maxAircraftGroundspeed;
        }

        function setMaxAircraftGroundspeed($speed)
        {
            $this->maxAircraftGroundspeed = $speed;
            return $this;
        }

        function getStandExtensions()
        {
            return $this->standExtensions;
        }

        function setStandExtensions($standArray)
        {
            $this->standExtensions = $standArray;
            return $this;
        }


    }

?>
