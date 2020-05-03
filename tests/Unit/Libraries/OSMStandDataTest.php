<?php


namespace Tests\Unit\Libraries;


use CobaltGrid\VatsimStandStatus\Exceptions\CoordinateOutOfBoundsException;
use CobaltGrid\VatsimStandStatus\Exceptions\InvalidICAOCodeException;
use CobaltGrid\VatsimStandStatus\Libraries\DecimalCoordinateHelper;
use CobaltGrid\VatsimStandStatus\Libraries\OSMStandData;
use GuzzleHttp\Client;
use Mockery;
use Tests\TestCase;

class OSMStandDataTest extends TestCase
{
    private $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new OSMStandData('EGKK');

        // Delete any generated files
        $this->instance->deleteCachedData();
    }

    public function testItThrowsIfInvalidICAOCode()
    {
        $this->expectException(InvalidICAOCodeException::class);
        new OSMStandData('1234');

        $this->expectException(InvalidICAOCodeException::class);
        new OSMStandData('EGK');
    }

    public function testItCanBeConstructed()
    {
        $this->expectNotToPerformAssertions();
        new OSMStandData('EGKK', new Client());
    }

    public function testItDefersToCachedData()
    {
        $client = Mockery::spy(Client::class);
        $instance = new OSMStandData('EGKK', $client);
        // Insert the file
        file_put_contents($this->instance->getCacheFilePath(), '');

        $this->assertEquals($this->instance->getCacheFilePath(), $instance->fetchStandData(1,1));
        $client->shouldNotHaveBeenCalled();
    }

    public function testItCanParseExampleAPIResponse()
    {
        $responseFile = file_get_contents(dirname(__FILE__).'/../../Fixtures/OSMExample/exampleAPIResponse.csv');
        $parsedFile = str_replace("\r", "", file_get_contents(dirname(__FILE__).'/../../Fixtures/OSMExample/expectedOutput.csv'));

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get->getBody->getContents')
            ->andReturn($responseFile);

        $instance = new OSMStandData('EGKK', $client);

        $this->assertEquals($this->instance->getCacheFilePath(), $instance->fetchStandData(1, 1, 20));
        $this->assertEquals($parsedFile, file_get_contents($this->instance->getCacheFilePath()));
    }

    public function testItCanSetCacheTTL()
    {
        $this->assertInstanceOf(OSMStandData::class, $this->instance->setCacheTTL(60));
    }

    public function testItCanSetTimeout()
    {
        $this->assertInstanceOf(OSMStandData::class, $this->instance->setTimeout(60));
    }

    public function testItCanDeleteCachedData()
    {
        // Insert the file
        file_put_contents($this->instance->getCacheFilePath(), '');

        $this->assertTrue($this->instance->deleteCachedData());

        // No file to delete, expect false
        $this->assertFalse($this->instance->deleteCachedData());
    }

}