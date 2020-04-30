# vatsim-stand-status

![Stand Status CI](https://github.com/atoff/vatsim-stand-status/workflows/Stand%20Status%20CI/badge.svg)

## About

#### Description
vatsim-stand-status is a lightweight PHP library to allow the correlation between aircraft on the VATSIM flight simulation network, and an airport stand.

VATSIM network data is downloaded and parsed by [Skymeyer's Vatsimphp](https://github.com/skymeyer/Vatsimphp) library.


#### Requirements
* PHP 7.2 and above

#### Author
This package was created by [Alex Toff](https://alextoff.uk)

#### License
`vatsim-stand-status` is licensed under the GNU General Public License v3.0, which can be found in the root of the package in the `LICENSE` file.

## Installation

The easiest way to install stand status is through the use of composer:
```
$ composer require cobaltgrid/vatsim-stand-status
```

## Configuration
You can configure various variables using the setters in the `StandStatus` class file. Once changing one (or many) of these values, you must run `$StandStatus->parseData()` so that the data is reloaded with the new settings. To be more efficient, set the `$parseData` argument in the constructor to false, set your new config settings, and then parse the data. Note: the setters here all return the class instance, so you can chain them together.


```
     private $maxStandDistance = 0.07; // In kilometeres
```
* This is the maximum an aircraft can be from a stand (in km) for that stand to be considered in the search for matching aircraft with stand
* Getter: `$StandStatus->getMaxStandDistance()`
* Setter: `$StandStatus->setMaxStandDistance($distance)`
```
     private $hideStandSidesWhenOccupied = true;
```
* If true, stand sides (such as 42L and 42R) will be hidden when an aircraft occupies the 'base' stand, or a side. I.E If the system determines the aircraft is occupying stand 42, the stands 42L and 42R will be removed from the list of stands
* Getter: `$StandStatus->getHideStandSidesWhenOccupied()`
* Setter: `$StandStatus->setHideStandSidesWhenOccupied($bool)`
```
     private $maxDistanceFromAirport = 2; // In kilometeres
```
Note: There is no need to manually override this value here, as you can pass a value to the constructor instead.
* This is one of the first checks used to filter down the global VATSIM pilots into possible pilots. Aircraft that are within this distance from the defined center of the airport will be used in later filtering.
* Getter: `$StandStatus->getMaxDistanceFromAirport()`
* Setter: `$StandStatus->setMaxDistanceFromAirport($distance)`
```
     private $maxAircraftAltitude = 3000; // In feet
```
* This value is used in a check that ensures that possible aircraft are at or below this altitude.
* Getter: `$StandStatus->getMaxAircraftAltitude()`
* Setter: `$StandStatus->setMaxAircraftAltitude($altitude)`
```
     private $maxAircraftGroundspeed = 10; // In knots
```
* This value is used in a check that ensures that possible aircraft are at or below this ground speed.
* Getter: `$StandStatus->getMaxAircraftGroundspeed()`
* Setter: `$StandStatus->setMaxAircraftGroundspeed($speed)`
```
     private $standExtensions = array("L", "C", "R", "A", "B");
```
* ___TLDR; Not implemented.___ These letters are possible extensions for 'base' stands. Many airports, such as Gatwick, have stands on the side of a main stand, usually used for aircraft that do not require the full width. For example, stand 53 comprises of 53, 53L and 53R. You can add more extensions here. Not implemented yet, however.
* Getter: `$StandStatus->getStandExtensions()`
* Setter: `$StandStatus->setStandExtensions($standArray)`


## Usage

#### The CSV File
The CSV file currently has to follow an exact format. A couple of examples of these files can be found in examples/standData

The first row is reserved for headers. They should be `id`, `latcoord` and `longcoord` (I have not tested using other headers)

* In the `id` column is the stand name. This can be text, such as "42L", and doesn't just have to be a number.
* In the `latcoord` column as current, you __MUST__ have the weird latitude format the the CAA uses on their stand data documents, as the program is currently hardcoded to convert these into the normal decimal coordinates. (e.g 510917.35N)
* In the `longcoord` column, just like the `latcoord` column, you __MUST__ have the weird latitude format the the CAA uses on their stand data documents (e.g 0000953.33W)
***
If you would like to code a fix for this, feel free to submit a PR for it :) 
***

In the end, you should have a CSV file that looks something like this:

| id        	| latcoord      | longcoord  	|
| ------------- |:-------------:| :----:	|
| 1 		| 10917.35N 	| 0000953.33W 	|
| 2 		| 510915.83N    | 0000952.81W 	|
| 3		| 510914.31N    | 0000952.28W 	|	

#### The construction

The best way to use this library is by using Composer Autoloader:
```
     require('./vendor/autoload.php');
     use CobaltGrid\VatsimStandStatus\StandStatus;
```


Then, an instance of the class must be made:
```
$StandStatus = new StandStatus($airportICAO, $airportStandsFile, $airportLatCoordinate, $airportLongCoordinate, $parseData = true, $minAirportDistance = null);
```
* `$airportICAO` - The 4 letter airport ICAO code. No real use as of yet.
* `$airportStandsFile` - The absolute path to the CSV file you should have created.
* `$airportLatCoordinate` - The decimal-format version of the airports latitude. E.G 51.148056
* `$airportLongCoordinate` - The decimal-format version of the airports longitude. E.G -0.190278
* `$parseData` - Boolean. Sets whether or not the data should be parsed immediately. Set to false when you plan on changing the default variables in "Configuration"
* `$maxAirportDistance ` - The maximum distance filtered aircraft can be from the airport coordinates in kilometers.

Here is an example:

`$StandStatus = new StandStatus("EGKK", dirname(__FILE__) . "/standData/egkkstands.csv", 51.148056, -0.190278, true, 3);`

Once this step has done, the data file is downloaded and processed, and stands with aircraft close enough to them, and that fit within the requirements, are marked as occupied by the associated aircraft. All of the aircraft's data is assigned to the stand, allowing you to access many variables from the network data:
```
callsign:cid:realname:clienttype:frequency:latitude:longitude:altitude:groundspeed:planned_aircraft:planned_tascruise:planned_depairport:planned_altitude:planned_destairport:server:protrevision:rating:transponder:facilitytype:visualrange:planned_revision:planned_flighttype:planned_deptime:planned_actdeptime:planned_hrsenroute:planned_minenroute:planned_hrsfuel:planned_minfuel:planned_altairport:planned_remarks:planned_route:planned_depairport_lat:planned_depairport_lon:planned_destairport_lat:planned_destairport_lon:atis_message:time_last_atis_received:time_logon:heading:QNH_iHg:QNH_Mb:
```
__Possible Stand Array Formats__

If a stand is occupied, you are able to fetch the details of the aircraft by accessing the stands "occupied" index (As stands are passed as an array). Here is an example for an occupied stand:

| id        	| latcoord      | longcoord  	| occupied  	|
| ------------- |:-------------:| :----:	| :----:	| 
| 1 		| 51.148056 	| -0.190278	| array(...)	|

and a unoccupied stand:

| id        	| latcoord      | longcoord  	|
| ------------- |:-------------:| :----:	|
| 1 		| 51.148056 	| -0.190278	|
#### The use

There are 3 main functions that you can use from the `$StandStatus` instance:

___allStands($pageNo = null, $pageLimit = null)___
>This function returns an array of ALL of the possible stands. All stands from the CSV are returned (with the exception of occupied stand's side stands if enabled), and each stand follows the data format shown under Possible Stand Array Formats. Returns an array of stands.

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

