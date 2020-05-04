<?php

namespace CobaltGrid\VatsimStandStatus;

use Exception;

class Aircraft
{

    private $vatsimData;
    private $standIndex;

    /**
     * Aircraft constructor.
     * @param array $vatsimData Pilot data from VATSIM data file
     */
    public function __construct(array $vatsimData)
    {
        $this->vatsimData = $vatsimData;
    }

    /**
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        if (isset($this->vatsimData[$name])) {
            return $this->vatsimData[$name];
        }

        return null;
    }

    /**
     * @return string
     */
    public function getStandIndex()
    {
        return $this->standIndex;
    }

    /**
     * @param array $stands Master list of stands
     * @return Stand|null
     */
    public function getStand($stands)
    {
        return $this->standIndex ? $stands[$this->standIndex] : null;
    }

    /**
     * Returns if the aircraft is on a stand or not
     *
     * @return bool
     */
    public function onStand()
    {
        return !!$this->standIndex;
    }

    /**
     * @param string $standIndex
     */
    public function setStandIndex($standIndex)
    {
        $this->standIndex = $standIndex;
    }
}