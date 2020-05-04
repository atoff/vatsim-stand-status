<?php


namespace Tests\Unit\Libraries;


use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Libraries\DecimalCoordinateHelper;
use Tests\TestCase;

class DecimalCoordinateHelperTest extends TestCase
{
    private $moscowCoords = [55.755833, 37.617222];
    private $newYorkCoords = [40.661, -73.944];

    public function testItThrowsWithInvalidCoordinates()
    {
        $this->expectException(CoordinateOutOfBoundsException::class);
        DecimalCoordinateHelper::validateCoordinatePairOrFail(1000, 0.1);
    }

    public function testItCanValidateALatitudeCoordinate()
    {
        $this->assertTrue(DecimalCoordinateHelper::validateLatitudeCoordinate(0));
        $this->assertTrue(DecimalCoordinateHelper::validateLatitudeCoordinate(-1.2123413));
        $this->assertTrue(DecimalCoordinateHelper::validateLatitudeCoordinate(89.00123));
        $this->assertTrue(DecimalCoordinateHelper::validateLatitudeCoordinate(90));
        $this->assertTrue(DecimalCoordinateHelper::validateLatitudeCoordinate(-90));

        $this->assertFalse(DecimalCoordinateHelper::validateLatitudeCoordinate(90.1));
        $this->assertFalse(DecimalCoordinateHelper::validateLatitudeCoordinate(-90.1));
    }

    public function testItCanValidateALongitudeCoordinate()
    {
        $this->assertTrue(DecimalCoordinateHelper::validateLongitudeCoordinate(0));
        $this->assertTrue(DecimalCoordinateHelper::validateLongitudeCoordinate(156.01231));
        $this->assertTrue(DecimalCoordinateHelper::validateLongitudeCoordinate(-156.0123));
        $this->assertTrue(DecimalCoordinateHelper::validateLongitudeCoordinate(180));
        $this->assertTrue(DecimalCoordinateHelper::validateLongitudeCoordinate(-180));

        $this->assertFalse(DecimalCoordinateHelper::validateLongitudeCoordinate(180.1));
        $this->assertFalse(DecimalCoordinateHelper::validateLongitudeCoordinate(-180.1));
    }

    public function testItCanFindDistanceBetweenTwoCoordinates()
    {
        // Moscow to New York
        $this->assertEquals(7512, round(DecimalCoordinateHelper::distanceBetweenCoordinates(...array_merge($this->moscowCoords, $this->newYorkCoords))));

        // Flip direction
        $this->assertEquals(7512, round(DecimalCoordinateHelper::distanceBetweenCoordinates(...array_merge($this->newYorkCoords,$this->moscowCoords))));
    }

    public function testItCanFindCoordinatesFromDistanceAndBearing()
    {
        $set1 = DecimalCoordinateHelper::distanceFromCoordinate($this->newYorkCoords[0], $this->newYorkCoords[1], 10, 45);
        $set2 = DecimalCoordinateHelper::distanceFromCoordinate($this->newYorkCoords[0], $this->newYorkCoords[1], 10, 195);
        $this->assertEquals(40.72456128648007, $set1->latitude);
        $this->assertEquals(-73.86008993799598, $set1->longitude);

        $this->assertEquals(40.574128150080696, $set2->latitude);
        $this->assertEquals( -73.9746440460457, $set2->longitude);
    }

}