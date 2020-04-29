<?php

namespace CobaltGrid\VatsimStandStatus;

use CobaltGrid\VatsimStandStatus\Exceptions\InvalidStandException;

class Stand
{

    /* The Stand Name or Identifier (e.g. 21L) */
    private $id;

    /* The Defined Stand Latitude In Decimal Format (e.g. 51.148056) */
    private $latitude;

    /* The Defined Stand Longitude In Decimal Format (e.g. -0.190278) */
    private $longitude;

    /* The Stand Occupier. Instance of Aircraft */
    private $occupier;

    /**
     * Stand constructor.
     * @param string|int $id
     * @param float $latitude
     * @param float $longitude
     * @param Aircraft|null $occupier
     * @throws InvalidStandException
     */
    public function __construct($id, $latitude, $longitude, Aircraft $occupier = null)
    {
        $this->id = (string)$id;
        if ($this->id == null) {
            throw new InvalidStandException("An invalid stand ID/name was passed to the Stand constructor: '{$id}'");
        }
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->occupier = $occupier;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }

    /**
     * @param Aircraft $aircraft
     */
    public function setOccupier(Aircraft $aircraft)
    {
        $this->occupier = $aircraft;
    }

    /**
     * @return bool
     */
    public function isOccupied()
    {
        return !!$this->occupier;
    }

    /**
     * @return string
     */
    public function getIndex()
    {
        return $this->getName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->id;
    }

    /**
     * Finds and returns the stand number without an extension
     *
     * @param string $pattern Regex matching pattern
     * @return string|null
     */
    public function getRoot($pattern)
    {
        return preg_replace($pattern, '', $this->getName());
    }

    /**
     * Finds and returns the stand's extension (if exists)
     *
     * @param string $pattern Regex matching pattern
     * @return string|null
     */
    public function getExtension($pattern)
    {
        $gotMatch = preg_match($pattern, $this->getName(), $matches);
        if(!$gotMatch){
            return null;
        }

        return $matches[0];
    }

}