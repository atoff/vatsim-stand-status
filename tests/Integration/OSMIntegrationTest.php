<?php


namespace Tests\Integration;


use CobaltGrid\VatsimStandStatus\Exceptions\InvalidCoordinateFormat;
use CobaltGrid\VatsimStandStatus\Libraries\OSMStandData;
use CobaltGrid\VatsimStandStatus\StandStatus;
use Tests\TestCase;

class OSMIntegrationTest extends TestCase
{
    public function testItRequiresDecimalFormat()
    {
        $this->expectException(InvalidCoordinateFormat::class);
        $instance = new StandStatus(51.148056,-0.190278, StandStatus::COORD_FORMAT_CAA);
        $instance->fetchAndLoadStandDataFromOSM('EGKK');
    }

    public function testItCanLoadStandDataFromOSM()
    {
        // Delete file if has
        $OSMInstance = new OSMStandData('EGKK');
        $OSMInstance->deleteCachedData();

        $instance = new StandStatus(51.148056,-0.190278);
        $instance->fetchAndLoadStandDataFromOSM('EGKK');
        $this->assertTrue(count($instance->stands()) > 1);

        // Delete data file generated
        $OSMInstance->deleteCachedData();
    }
}