<?php


namespace Tests\Integration;


use CobaltGrid\VatsimStandStatus\StandStatus;
use Tests\TestCase;

class StandStatusIntegrationTest extends TestCase
{
    public function testItCanLoadPilotDataFromVATSIMPHP()
    {
        $this->expectNotToPerformAssertions();
        $instance = new StandStatus(51.148056,-0.190278, StandStatus::COORD_FORMAT_CAA);
        $instance->loadStandDataFromCSV(dirname(__FILE__) . '/../Fixtures/SampleData/egkkstands.csv')->parseData();
    }
}