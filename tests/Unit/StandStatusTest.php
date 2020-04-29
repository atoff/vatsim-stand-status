<?php


namespace Tests\Unit;

use CobaltGrid\VatsimStandStatus\Aircraft;
use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidStandException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToLoadStandDataFileException;
use CobaltGrid\VatsimStandStatus\Exceptions\UnableToParseStandDataException;
use CobaltGrid\VatsimStandStatus\Stand;
use CobaltGrid\VatsimStandStatus\StandStatus;
use Tests\TestCase;

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

    public function testItFiltersPilotsCorrectly()
    {
        $this->instance->parseData();
        $this->assertEquals(['TEST1', 'TEST2', 'TEST5'], array_map(function(Aircraft $aircraft){
            return $aircraft->callsign;
        }, $this->instance->getAllAircraft()));
    }

    public function testItAssignsStandsCorrectly()
    {
        $this->instance->parseData();
        $this->assertNull($this->instance->getAllAircraft()[0]->getStandIndex());
        $this->assertNull($this->instance->getAllAircraft()[1]->getStandIndex());
        $this->assertEquals('43N', $this->instance->getAllAircraft()[2]->getStandIndex());
    }
}