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

    private $standExtensions;
    private $standPattern;

    /**
     * Stand constructor.
     *
     * @param string|int $id Stand Identifier. e.g. 25L
     * @param float $latitude Stand Latitude
     * @param float $longitude Stand Longitude
     * @param array $standExtensions Array of stand extension. e.g. ['L', 'B']
     * @param string $standPattern The stand pattern
     * @param Aircraft|null $occupier Optional stand occupier
     * @throws InvalidStandException
     */
    public function __construct($id, $latitude, $longitude, $standExtensions, $standPattern, Aircraft $occupier = null)
    {
        $this->id = (string)$id;
        if ($this->id == null) {
            throw new InvalidStandException("An invalid stand ID/name was passed to the Stand constructor: '{$id}'");
        }
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->occupier = $occupier;
        $this->standExtensions = $standExtensions;
        $this->standPattern = $standPattern;
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
    public function setOccupier(?Aircraft $aircraft)
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

    public function isPartOfOccupiedGroup()
    {
        return $this->isOccupied() && $this->occupier->getStandIndex() !== $this->getKey();
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getKey();
    }

    /**
     * Finds and returns the stand number without an extension
     *
     * @return string|null
     */
    public function getRoot()
    {
        if(!$matches = $this->matchNameAgainstRegex()){
            return null;
        }

        if(count($matches) < 3){
            // No extension
            return $matches[1];
        }

        return $this->standRootComesFirst() ? $matches[1] : $matches[2];
    }

    /**
     * Finds and returns the stand's extension (if exists)
     *
     * @return string|null
     */
    public function getExtension()
    {
        if(!$matches = $this->matchNameAgainstRegex()){
            return null;
        }

        if(count($matches) < 3){
            // No extension
            return null;
        }

        return !$this->standRootComesFirst() ? $matches[1] : $matches[2];
    }

    /**
     *  Resets the stand to remove any matched aircraft
     */
    public function clearParsedData()
    {
        $this->occupier = null;
    }

    /**
     * Matches the name against the extension regex
     *
     * @return array|null
     */
    private function matchNameAgainstRegex()
    {
        $gotMatch = preg_match($this->generateExtensionRegex(), $this->getName(), $matches);
        if (!$gotMatch) {
            return null;
        }
        return $matches;
    }

    /**
     * Generates the regex to capture a stand's extension
     *
     * @return string
     */
    private function generateExtensionRegex()
    {
        // Compose regex
        $extensions = "(" . implode('|', $this->standExtensions) . ")?";
        $pattern = '/^' . $this->standPattern . '$/';
        return str_replace(['<standroot>', '<extensions>'], ['([0-9]+)', $extensions], $pattern);
    }

    /**
     * Returns whether in the stand pattern, the root or extension comes first
     *
     * @return bool;
     */
    private function standRootComesFirst()
    {
        return strpos($this->standPattern, '<standroot>') < strpos($this->standPattern, '<extensions>');
    }

}