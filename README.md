# vatsim-stand-status

## About

#### Description
vatsim-stand-status is a fairly lightweight library to allow correlation of aircraft on the VATSIM network with known aircraft stand coordinates. 

Data is retrieved from the offical VATSIM network data sources through the use of [Skymeyer's Vatsimphp](https://github.com/skymeyer/Vatsimphp) libaray.


#### Requirements
* PHP 5.3.29 and above (For skymeyer/Vatsimphp)

#### Author
This package was created by [Alex Toff](http://alex.coblatgrid.com)

#### License
vatsim-stand-status is licensed under the GNU General Public License v3.0, which can be found in the root of the package in the `LICENSE` file.

## Installation

The easiest way to install stand status is through the use of composer:
```
$ composer require cobaltgrid/vatsim-stand-status
```

## Configuration
At the moment, the configuration of the library is  slightly odd, and must be done through editing the main source file, `src/StandStatus.php`.

The main place where you may (or may not) want to edit the default values is at around Lines 26-31:
```
     private $maxStandDistance = 0.07; // In kilometeres
```
* This is the maxium an aircraft can be from a stand (in km) for that stand to be considered in the search for matching aircraft with stand
```
     private $hideStandSidesWhenOccupied = true;
```
* If true, stand sides (such as 42L and 42R) will be hidden when an aircraft occupies the 'base' stand, or a side. I.E if the aircraft is closest to 42, the stands 42L and 42R will be removed from the list of stands
```
     private $maxDistanceFromAirport = 2; // In kilometeres
```
Note: There is no need to manually override this value here, as you can pass a value to the constructor instead.
* This is one of the first checks used to filter down the global VATSIM pilots into possible pilots. Aircraft that are within this distance from the defined center of the airport will be used in later filtering.
```
     private $maxAircraftAltitude = 3000; // In feet
```
* This value is used in a check that ensures that possible aircraft are at or below this altitude.
```
     private $maxAircraftGroundspeed = 10; // In knots
```
* This value is used in a check that ensures that possible aircraft are at or below this ground speed.
```
     private $standExtensions = array("L", "C", "R", "A", "B");
```
* ___TLDR; Not implemented.___ These letters are possible extensions for 'base' stands. Many airports, such as Gatwick, have stands on the side of a main stand, usually used for aircraft that do not require the full width. For example, stand 53 comprises of 53, 53L and 53R. You can add more extensions here. Not implemented yet, however.
* 

## Usage

#### The CSV File
The CSV file currently has to follow an exact format. A couple of examples of these files can be found in examples/standData

The first row is reserved for headers. They should be `id`, `latcoord` and `longcoord` (I have not tested using other headers)

* In the `id` column is the stand name. This can be text, such as "42L", and not just a number.
* In the `latcoord` column as current, you __MUST__ have the weird latitude format the the CAA uses on their stand data documents, as the program is currently hardcoded to convert these into the normal decimal coordinates. (e.g 510917.35N)
* In the `longcoord` column, just like the `latcoord` column, you __MUST__ have the weird latitude format the the CAA uses on their stand data documents (e.g 0000953.33W)
***
If you would like to code a fix for this, feel free to submit a PR for it :) 
***

In the end, you should have a CSV file that looks something like this:

| id        	| latcoord      | longcoord  |
| ------------- |:-------------:| :----:	|
| 1 			| 10917.35N 	| 0000953.33W |
| 2 			| 510915.83N      |   0000952.81W |
| 3			 	| 510914.31N      |    0000952.28W |

#### The construction

The library is used by first (a) using the composer class autoloader and then 'using' the class `use CobaltGrid\VatsimStandStatus\StandStatus;` or (b) using require/include to import the class into the current file `require_once('...../src/StandStatus.php')`

Then, an instance of the class must be made:
```
$StandStatus = new StandStatus($airportICAO, $airportStandsFile, $airportLatCoordinate, $airportLongCoordinate, $minAirportDistnace = null);
```
* `$airportICAO` - The 4 letter airport ICAO code. No real use as of yet.
* `$airportStandsFile` - The absolute path to the CSV file you should have created.
* `$airportLatCoordinate` - The decimal-format version of latitude. E.G 51.148056
* `$airportLongCoordinate` - The decimal-format version of latitude. E.G -0.190278
* `$maxAirportDistance ` - The maximum distance filtered aircraft can be from the airport coordinates in kilometers.

Here is an example:

`$StandStatus = new StandStatus("EGKK", dirname(__FILE__) . "/standData/egkkstands.csv", 51.148056, -0.190278, 3);`

Once this step has done, the data file is downloaded and processed, and stands with aircraft close enough to them, and that fit within the requirements, are marked as occupied by the associated aircraft. All of the aircraft's data is assigned to the stand, allowing you to access many variables from the network data:
```
callsign:cid:realname:clienttype:frequency:latitude:longitude:altitude:groundspeed:planned_aircraft:planned_tascruise:planned_depairport:planned_altitude:planned_destairport:server:protrevision:rating:transponder:facilitytype:visualrange:planned_revision:planned_flighttype:planned_deptime:planned_actdeptime:planned_hrsenroute:planned_minenroute:planned_hrsfuel:planned_minfuel:planned_altairport:planned_remarks:planned_route:planned_depairport_lat:planned_depairport_lon:planned_destairport_lat:planned_destairport_lon:atis_message:time_last_atis_received:time_logon:heading:QNH_iHg:QNH_Mb:
```
__Possible Stand Array Formats__

If a stand is occupied, you are able to fetch the details of the aircraft by accessing the stands "occupied" index (As stands are passed as an array). Here is an example for an occupied stand:
| id        	| latcoord      | longcoord  	| occupied  	|
| ------------- |:-------------:| :----:		| :----:		| 
| 1 			| 51.148056 	| -0.190278	| array(...)	|

and a unoccupied stand:
| id        	| latcoord      | longcoord  	|
| ------------- |:-------------:| :----:		|
| 1 			| 51.148056 	| -0.190278	|
#### The use

There are 3 functions to use to gather the various collections of stands from the `$StandStatus` instance:

___allStands($pageNo = null, $pageLimit = null)___
>This function returns an array of ALL of the possible stands. All proccessed stands from the CSV are returned (with the exception of occupied stand's side stands if enabled). Returns an array of stands.

>If $pageNo and $pageLimit are specified, only $pageLimit ammount of stands will be returned. Useful for pagination


Usage
```
foreach ($stand as $StandStatus->allStands())
{
	if (isset($stand['occupied'])) {
    	echo "Stand " . $stand['id'] . " is occupied by " . $stand['occupied']['callsign'] . "</br>";
    }else{
    	echo "Stand " . $stand['id'] . " is not occupied </br>";
    }	
}
// Output:
// Stand 1 is occupied by SHT1G
// Stand 2 is not occupied
```

___occupiedStands($pageNo = null, $pageLimit = null)___
>This function returns an array of only the occupied stands (with the exception of occupied stand's side stands if enabled). Returns an array of stands.

>If $pageNo and $pageLimit are specified, only $pageLimit ammount of stands will be returned. Useful for pagination

Usage
```
foreach ($stand as $StandStatus->occupiedStands())
{
    	echo "Stand " . $stand['id'] . " is occupied by " . $stand['occupied']['callsign'] . "</br>";
}
// Output:
// Stand 1 is occupied by SHT1G
```

___allStandsPaginationArray($pageLimit = null)___
>Kind of WIP. This function will return an array, sliced into pages of $pageLimit, ready for you to access each page. It currently only used the allStands() function.


Usage
```
$pages = $StandStatus->allStandsPaginationArray(10);

foreach ($stand as $pages[0])
{
    	echo "Stand " . $stand['id'] . "</br>";
}
echo "Page 2 </br>";
foreach ($stand as $pages[1])
{
    	echo "Stand " . $stand['id'] . "</br>";
}

// Output:
// Stand 1
// .....
//
// Page 2
// Stand 10
```

