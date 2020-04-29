<?php


namespace CobaltGrid\VatsimStandStatus\Libraries;


abstract class CoordinateConverter
{
    protected $latitude;
    protected $longitude;

    public function __construct($latitude = null, $longitude = null)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Converts Degrees, minutes and seconds into a decimal format coordinate
     *
     * @param int $degrees
     * @param int $minutes
     * @param int $seconds
     * @param bool|null $negative
     * @return float|int
     */
    protected function convertDMSToDecimal($degrees, $minutes, $seconds, $negative = null)
    {
        // Deduce sign if not given
        if(!$negative){
            // Find sign from the sign of the degrees integer. If positive, assume East / North
            if($degrees >= 0){
                $negative = false;
            }else{
                $negative = true;
            }
        }

        // Converting DMS ( Degrees / minutes / seconds ) to decimal format
        $float = $degrees + ((($minutes * 60) + ($seconds)) / 3600);
        return $negative ? -1 * $float : $float;
    }

    abstract public function latitudeToDecimal();
    abstract public function longitudeToDecimal();
}