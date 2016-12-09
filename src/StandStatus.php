<?php
namespace CobaltGrid\VatsimStandStatus;

use Vatsimphp\VatsimData;

class StandStatus {

    public $stands = array();
    public $occupiedStands = array();
    public $aircraftSearchResults;

    /*
     Data Source
    */

    private $VATSIMDataAPI = "http://api.vateud.net/online/pilots/egkk.json";

    /*
     Database
    */

    private $databaseHost = "localhost";
    private $databaseUser = "root";
    private $databasePassword = "";
    private $databaseTable = "";

    public $databaseConnection = null;

    /*
     Airport Stand Details
    */

    public $airportICAO;
    public $airportName;
    public $airportBounds;

    public $airportStandsFile;

    /*
      Configuration
     */

     private $minStandDistance = 0.06;
     private $hideStandSidesWhenOccupied = true;


    public function __construct($airportICAO, $airportStandsFile) {
      $this->airportICAO = $airportICAO;
      $this->airportStandsFile = $airportStandsFile;
      $this->loadStandsData();
      $this->getAircraftWithinParameters();
      $this->checkIfAircraftAreOnStand();
    }


    // Load the stand data
    function loadStandsData(){
      $array = $fields = array(); $i = 0;
      $handle = @fopen($this->airportStandsFile, "r");
      if ($handle) {
          while (($row = fgetcsv($handle, 4096)) !== false) {
              if (empty($fields)) {
                  $fields = $row;
                  continue;
              }
              $y = 0;
              foreach ($row as $k=>$value) {
                  if($y == 1){ // Convert LAT coordinate
                    $array[$row[0]][$fields[$k]] = $this->convertCAALatCoord($value);
                  }else if($y == 2){ // Convert LONG coordinate
                    $array[$row[0]][$fields[$k]] = $this->convertCAALongCoord($value);
                  }else{
                    $array[$row[0]][$fields[$k]] = $value;
                  }
                  $y++;
              }
              $i++;
          }
          if (!feof($handle)) {
              echo "Error: unexpected fgets() fail\n";
          }
          fclose($handle);
      }
      $this->stands = $array;
    }

    function getAircraftWithinParameters(){
      $vatsim = new VatsimData();
      $vatsim->loadData();

      $pilots = $vatsim->getPilots()->toArray();

      $icao = "EGKK";

      $filteredResults = array();
      foreach($pilots as $pilot){
        if(($this->getCoordDistance($pilot['latitude'], $pilot['longitude'], 51.148056, -0.190278) < 2) ){
          if(($pilot['groundspeed'] < 10) && ($pilot['altitude'] < 500)){
           $filteredResults[] = $pilot;
         }
        }

      }
      $this->aircraftSearchResults = $filteredResults;
    }

    function checkIfAircraftAreOnStand(){
      $pilots = $this->aircraftSearchResults;
      $stands = $this->stands;
      $standDistanceBoundary = $this->minStandDistance;

      foreach($pilots as $pilot){

        // Array to hold the stands they could possibly be on
        $possibleStands = array();

        // Check each stand to see how close they are
        foreach ($stands as $stand) {

          // Find distance between aircraft and stand
          $distance = $this->getCoordDistance($stand['latcoord'], $stand['longcoord'], $pilot['latitude'], $pilot['longitude'] );

          if($pilot['callsign'] == "EZY56WU"){
          //echo round($distance, 4) . " from stand " . $stand['id'] . "</br>";
          }

          if( $distance < $standDistanceBoundary ) {
            if($pilot['callsign'] == "EZY56WU"){
              //echo $stand['id'] . "</br>";
            }
            //echo round($distance, 4) . " from stand " . $stand['id'] . "</br>";
            // This could be a possible stand as the aircraft is close
            $possibleStands[] = array('id' => $stand['id'], 'distance' => $distance);
          }

        }

        // Check how many stands are possible
        if(count($possibleStands) > 1){

          $minDistance = $standDistanceBoundary; // Cant be more than $standDistanceBoundary
          $minStandID = null;

          foreach($possibleStands as $stand){

            if($stand['distance'] < $minDistance){
              // New smallest distance from stand
              $minDistance = $stand['distance'];
              $minStandID = $stand['id'];
            }

          }
          $this->checkAndSetStandOccupied($minStandID, $pilot);
          // setStandSidesOccupied($minStandID, $pilot);
          // $occstands[$minStandID] = $pilot;
        }else if (count($possibleStands) == 1) {
          $this->checkAndSetStandOccupied($possibleStands[0]['id'], $pilot);
          // setStandSidesOccupied(, $pilot);
          // $occstands[$possibleStands[0]['id']] = $pilot;
        }

      }
    }

    function checkAndSetStandOccupied($standID, $pilot){


      // Firstly set the acutal stand as occupied
      $this->setStandOccupied($standID, $pilot);

      // Check for side stands
      $standSides = $this->standSides($standID);
      if($standSides){

        foreach($standSides as $stand){
          $this->setStandOccupied($stand, $pilot);

        }
        if($this->hideStandSidesWhenOccupied){
          $standRoot = str_replace("R", "", $standID);
          $standRoot = str_replace("L", "", $standRoot);
          if(isset($this->stands[$standRoot])){
            if(isset($this->stands[$standRoot . "R"])){
              $this->unsetStandOccupied($standRoot . "R");
              unset($this->stands[$standRoot . "R"]);
            }
            if(isset($this->stands[$standRoot . "L"])){
              $this->unsetStandOccupied($standRoot . "L");
              unset($this->stands[$standRoot . "L"]);
            }
          }
        }
      }
    }

    function setStandOccupied($standID, $pilot){
      $this->stands[$standID]['occupied'] = $pilot;
      $this->occupiedStands[$standID] = $standID;
    }

    function unsetStandOccupied($standID){
      if(isset($this->stands[$standID]['occupied'])){
        unset($this->stands[$standID]['occupied']);
      }
      unset($this->occupiedStands[$standID]);
    }

    function standSides($standID){
      $standSides = array();
      $stands = $this->stands;
        // Check if stand has a side already
      if(strstr($standID, "R") || strstr($standID, "L")){
        // Our stand is already L/R
        if(strstr($standID, "R")){
          // Set the right hand side to occupied aswell
          $newStand = str_replace("R", "L", $standID);
          if(isset($stands[$newStand])){
            $standSides[] = $newStand;
          }
          // Set the base stand to occupied also
          $newStand = str_replace("R", "", $standID);
          if(isset($stands[$newStand])){
            $standSides[] = $newStand;
          }
        }else{
          // Set the left hand side to occupied aswell
          $newStand = str_replace("L", "R", $standID);
          if(isset($stands[$newStand])){
            $standSides[] = $newStand;
          }
        }
      }else{
        // Stand has no side, but may have L / R sides
        if(isset($stands[$standID . "L"])){
          $standSides[] = $standID . "L";
        }
        if(isset($stands[$standID . "R"])){
          $standSides[] = $standID . "R";
        }
      }

      if(count($standSides) == 0){
        return false;
      }else{
        return $standSides;
      }

    }


    /*

      Support Functions

     */

    function getCoordDistance($latitude1, $longitude1, $latitude2, $longitude2) {
     $earth_radius = 6371;

     $latitude1 = floatval($latitude1);
     $longitude1 = floatval($longitude1);
     $latitude2 = floatval($latitude2);
     $longitude2 = floatval($longitude2);

     $dLat = deg2rad( $latitude2 - $latitude1 );
     $dLon = deg2rad( $longitude2 - $longitude1 );

     $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
     $c = 2 * asin(sqrt($a));
     $d = $earth_radius * $c;

     return $d;

    }

    function convertCoordinateToDecimal($deg,$min,$sec,$dir){
    // Converting DMS ( Degrees / minutes / seconds ) to decimal format
    if($dir == "W"){
     return "-" . ($deg+((($min*60)+($sec))/3600));
    }else if($dir == "S"){
     return "-" . ($deg+((($min*60)+($sec))/3600));
    }
    return $deg+((($min*60)+($sec))/3600);
    }

    function convertCAALatCoord($coord){
    $deg = substr($coord, 0, 2);
    $min = substr($coord, 2, 2);
    $sec = substr($coord, 4,5);
    $dir = substr($coord, -1);
    return $this->convertCoordinateToDecimal($deg, $min, $sec, $dir);
    }

    function convertCAALongCoord($coord){
    $deg = substr($coord, 0, 3);
    $min = substr($coord, 3, 2);
    $sec = substr($coord, 5,5);
    $dir = substr($coord, -1);
    return $this->convertCoordinateToDecimal($deg, $min, $sec, $dir);
    }


}
?>
