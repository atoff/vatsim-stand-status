<?php


namespace Tests\Unit;

use CobaltGrid\VatsimStandStatus\Aircraft;
use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidStandException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToLoadStandDataFileException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToParseStandDataException;
use CobaltGrid\VatsimStandStatus\Stand;
use CobaltGrid\VatsimStandStatus\StandStatus;
use Mockery\Mock;
use Tests\TestCase;
use Vatsimphp\VatsimData;

class StandStatusTest extends TestCase
{
    private $standDataFileCAA;
    private $standDataFileDecimal;
    private $standDataFileInvalid;

    private $testPilots = [
        [ // Not on stand (on RVP)
            'callsign' => "TEST1",
            "latitude" => 51.148056,
            "longitude" => -0.190278,
            "altitude" => 0,
            "groundspeed" => 0
        ],
        [ // Not on stand
            'callsign' => "TEST2",
            "latitude" => 51.15092,
            "longitude" => -0.18304,
            "altitude" => 0,
            "groundspeed" => 5
        ],
        [ // Doesn't match altitude filter
            'callsign' => "TEST3",
            "latitude" => 51.15092,
            "longitude" => -0.18304,
            "altitude" => 5000,
            "groundspeed" => 5,
        ],
        [ // Doesn't match distance filter
            'callsign' => "TEST4",
            "latitude" => 53.15092,
            "longitude" => -0.18304,
            "altitude" => 0,
            "groundspeed" => 0,
        ],
        [ // On Stand 43N
            'callsign' => "TEST5",
            "latitude" => 51.15712,
            "longitude" => -0.17373,
            "altitude" => 0,
            "groundspeed" => 0,
        ]

    ];

    private $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->standDataFileCAA = dirname(__DIR__)."/Fixtures/SampleData/egkkstands.csv";
        $this->standDataFileDecimal = dirname(__DIR__)."/Fixtures/SampleData/decimalexample.csv";
        $this->standDataFileInvalid = dirname(__DIR__)."/Fixtures/SampleData/invalidexample.csv";

        $this->instance = \Mockery::mock(StandStatus::class, [
            $this->standDataFileCAA,
            51.148056,
            -0.190278,
            null,
            StandStatus::COORD_FORMAT_CAA,
            false
        ])->makePartial();
        $this->instance->shouldReceive('getVATSIMPilots')->andReturn($this->testPilots);
    }

    public function testItCanParseAutomatically()
    {
        $mock = \Mockery::mock(StandStatus::class)->makePartial();
        $mock->shouldReceive('getVATSIMPilots')->andReturn($this->testPilots);
        $mock->shouldReceive('parseData')->once();
        $mock->__construct(
            $this->standDataFileCAA,
            51.148056,
            -0.190278,
            null,
            StandStatus::COORD_FORMAT_CAA
        );
    }

    public function testItThrowsWithInvalidCoordinates()
    {
        $this->expectException(CoordinateOutOfBoundsException::class);
        new StandStatus($this->standDataFileCAA, 1000, 0.1);
    }

    public function testItThrowsIfItCantFindStandDataFile()
    {
        $this->expectException(UnableToLoadStandDataFileException::class);
        new StandStatus('', 0.1, 0.1);
    }

    public function testItThrowsIfItDataFileContentInvalid()
    {
        $this->expectException(UnableToParseStandDataException::class);
        new StandStatus($this->standDataFileInvalid, 0.1, 0.1);
    }

    public function testItParsesOk()
    {
        $this->instance->setHideStandSidesWhenOccupied(false)->parseData();
        $this->assertCount(186, $this->instance->allStands());
    }

    public function testItCanGetVATSIMPilots()
    {
        $mock = \Mockery::mock(VatsimData::class);
        $mock->shouldReceive('loadData')->andReturn(true);
        $mock->shouldReceive('getPilots->toArray')->andReturn($this->testPilots);

        $instance = $this->createNewInstance();
        $this->assertIsArray($instance->getVATSIMPilots($mock));
    }

    public function testGetVATSIMPilotsReturnsNullIfLoadDataFails()
    {
        $mock = \Mockery::mock(VatsimData::class);
        $mock->shouldReceive('loadData')->andReturn(false);

        $instance = $this->createNewInstance();
        $this->assertNull($instance->getVATSIMPilots($mock));
    }

    public function testItFiltersPilotsCorrectly()
    {
        $this->instance->parseData();
        $this->assertEquals(['TEST1', 'TEST2', 'TEST5'], array_map(function(Aircraft $aircraft){
            return $aircraft->callsign;
        }, $this->instance->getAllAircraft()));
    }

//    /** @only */
//    public function testItReturnsEmptyIfNoMatchesForAircraftSearch()
//    {
//        $instance = \Mockery::mock(StandStatus::class, [
//            $this->standDataFileCAA,
//            51.148056,
//            -0.190278,
//            null,
//            StandStatus::COORD_FORMAT_CAA,
//            false
//        ])->makePartial();
//        $instance->shouldReceive('getVATSIMPilots')->andReturn([]);
//        $instance->parseData();
//        $this->assertEmpty($instance->getAllAircraft());
//    }

    public function testItAssignsStandsCorrectly()
    {
        $this->instance->parseData();
        $this->assertNull($this->instance->getAllAircraft()[0]->getStandIndex());
        $this->assertNull($this->instance->getAllAircraft()[1]->getStandIndex());
        $this->assertEquals('43N', $this->instance->getAllAircraft()[2]->getStandIndex());
    }

    public function testItReturnsListOfOccupiedStands()
    {
        $this->instance->parseData();
        $this->assertCount(1, $this->instance->occupiedStands());
        $this->assertEquals('TEST5', $this->instance->occupiedStands()[0]->occupier->callsign);
        $this->assertEquals('TEST5', $this->instance->occupiedStands(true)['43N']->occupier->callsign);
    }

    public function testItReturnsListOfUnoccupiedStands()
    {
        $this->instance->parseData();
        // 186 stands, 1 occupied which includes 3 side stands = 183
        $this->assertCount(182, $this->instance->unoccupiedStands());
        $this->assertNull($this->instance->unoccupiedStands()[0]->occupier);
        $this->assertNull($this->instance->unoccupiedStands(true)['42']->occupier);
    }

    public function testGettersAndSetters()
    {
        $instance = $this->createNewInstance();

        $this->assertInstanceOf(StandStatus::class, $instance->setMaxStandDistance(1.11));
        $this->assertEquals(1.11, $instance->getMaxStandDistance());

        $this->assertInstanceOf(StandStatus::class, $instance->setHideStandSidesWhenOccupied(false));
        $this->assertFalse($instance->getHideStandSidesWhenOccupied());

        $this->assertInstanceOf(StandStatus::class, $instance->setMaxDistanceFromAirport(1.11));
        $this->assertEquals(1.11, $instance->getMaxDistanceFromAirport());

        $this->assertInstanceOf(StandStatus::class, $instance->setMaxAircraftAltitude(111));
        $this->assertEquals(111, $instance->getMaxAircraftAltitude());

        $this->assertInstanceOf(StandStatus::class, $instance->setMaxAircraftGroundspeed(11));
        $this->assertEquals(11, $instance->getMaxAircraftGroundspeed());

        $this->assertInstanceOf(StandStatus::class, $instance->setStandExtensions(['A', 'B', 'C']));
        $this->assertEquals(['A', 'B', 'C'], $instance->getStandExtensions());
    }

    private function createNewInstance()
    {
        return new StandStatus(
            $this->standDataFileCAA,
            51.148056,
            -0.190278,
            null,
            StandStatus::COORD_FORMAT_CAA,
            false);
    }
}