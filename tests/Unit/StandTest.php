<?php


namespace Tests\Unit;

use CobaltGrid\VatsimStandStatus\Aircraft;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidStandException;
use CobaltGrid\VatsimStandStatus\Stand;
use Tests\TestCase;

class StandTest extends TestCase
{
    private $instance;

    private $standID = "25R";
    private $standLatitude = 1.00000;
    private $standLongitude = 2.0000;
    private $standExtensions = ['L', 'R'];
    private $standPattern = '<standroot><extensions>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new Stand($this->standID, $this->standLatitude, $this->standLongitude, $this->standExtensions, $this->standPattern);
    }

    public function testItThrowsIfNullID()
    {
        $this->expectException(InvalidStandException::class);
        new Stand(null,1,2, $this->standExtensions, $this->standPattern);
    }

    public function testItThrowsIfEmptyID()
    {
        $this->expectException(InvalidStandException::class);
        new Stand('',1,2, $this->standExtensions, $this->standPattern);
    }

    public function testItMapsClassPropertiesToData()
    {
        $this->assertEquals($this->standID, $this->instance->id);
        $this->assertEquals($this->standLatitude, $this->instance->latitude);
        $this->assertEquals($this->standLongitude, $this->instance->longitude);
        $this->assertNull($this->instance->occupier);
        $this->assertNull($this->instance->invalid_property);
    }

    public function testItCanSetOccupier()
    {
        $this->assertFalse($this->instance->isOccupied());
        $this->instance->setOccupier(new Aircraft($this->testPilot));
        $this->assertTrue($this->instance->isOccupied());
        $this->instance->setOccupier(null);
        $this->assertFalse($this->instance->isOccupied());
    }

    public function testOccupierMustBeAircraftOrNull()
    {
        $this->expectException(\TypeError::class);
        $this->instance->setOccupier('Not an aircraft');
    }

    public function testItCanGetStandKey()
    {
        $this->assertEquals($this->standID, $this->instance->getKey());
    }

    public function testItCanGetStandName()
    {
        $this->assertEquals($this->standID, $this->instance->getName());
    }

    public function testItCanDetermineRoot()
    {
        $stand2 = new Stand('144', $this->standLatitude, $this->standLongitude, $this->standExtensions, $this->standPattern);
        $stand3 = new Stand('L73', $this->standLatitude, $this->standLongitude, $this->standExtensions, '<extensions><standroot>');

        $this->assertEquals('25', $this->instance->getRoot());
        $this->assertEquals('144', $stand2->getRoot());
        $this->assertEquals('73', $stand3->getRoot());
    }

    public function testItFailsGracefullyIfNoMatches()
    {
        $stand = new Stand('144R', $this->standLatitude, $this->standLongitude, $this->standExtensions, '');

        $this->assertNull($stand->getRoot());
        $this->assertNull($stand->getExtension());
    }

    public function testItCanDetermineExtension()
    {
        $stand2 = new Stand('144', $this->standLatitude, $this->standLongitude, $this->standExtensions, $this->standPattern);

        $this->assertEquals('R', $this->instance->getExtension());
        $this->assertNull($stand2->getExtension());
    }

}