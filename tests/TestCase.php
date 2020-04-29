<?php

namespace Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;

    protected $testPilot = [
        'callsign' => "TEST",
        "latitude" => 55.949228,
        "longitude" => -3.364303,
        "altitude" => 0,
        "groundspeed" => 0,
        "planned_destairport" => "TEST",
        "planned_depairport" => "TEST"
    ];
}