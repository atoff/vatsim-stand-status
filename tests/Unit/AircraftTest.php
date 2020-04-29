<?php


namespace Tests\Unit;

use CobaltGrid\VatsimStandStatus\Aircraft;
use Tests\TestCase;

class AircraftTest extends TestCase
{
    private $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new Aircraft($this->testPilot);
    }

    public function testItMapsClassPropertiesToData()
    {
        $this->assertEquals($this->testPilot['callsign'], $this->instance->callsign);
        $this->assertEquals($this->testPilot['latitude'], $this->instance->latitude);
        $this->assertNull($this->instance->unknown_index);
    }

    public function testItCanGetAndSetStandIndex()
    {
        $this->assertNull($this->instance->getStandIndex());
        $this->instance->setStandIndex('21R');
        $this->assertEquals('21R', $this->instance->getStandIndex());
    }

    public function testItCorrectlyReportsIfOnStand()
    {
        $this->assertFalse($this->instance->onStand());
        $this->instance->setStandIndex('21R');
        $this->assertTrue($this->instance->onStand());
    }
}