<?php


namespace CobaltGrid\VatsimStandStatus\Libraries;


class CAACoordinateConverter extends CoordinateConverter
{
    public function latitudeToDecimal()
    {
        $deg = substr($this->latitude, 0, 2);
        $min = substr($this->latitude, 2, 2);
        $sec = substr($this->latitude, 4, 5);
        $negative = substr($this->latitude, -1) == 'S';
        return $this->convertDMSToDecimal($deg, $min, $sec, $negative);
    }

    public function longitudeToDecimal()
    {
        $deg = substr($this->longitude, 0, 3);
        $min = substr($this->longitude, 3, 2);
        $sec = substr($this->longitude, 5, 5);
        $negative = substr($this->longitude, -1) == 'W';
        return $this->convertDMSToDecimal($deg, $min, $sec, $negative);
    }
}