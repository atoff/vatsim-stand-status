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
     * @param float $degrees
     * @param float $minutes
     * @param float $seconds
     * @param bool|null $negative
     * @return float|int
     */
    protected function convertDMSToDecimal($degrees, $minutes, $seconds, $negative = null)
    {
        // Casts
        $degrees = floatval($degrees);
        $minutes = floatval($minutes);
        $seconds = floatval($seconds);

        // Deduce sign if not given
        if($negative == null){
            // Find sign from the sign of the degrees integer. If positive, assume East / North
            if($degrees >= 0){
                $negative = false;
            }else{
                $negative = true;
            }
        }

        // Converting DMS ( Degrees / minutes / seconds ) to decimal format
        $float = abs($degrees) + ((($minutes * 60) + ($seconds)) / 3600);
        return $negative ? -1 * $float : $float;
    }

    abstract public function latitudeToDecimal();
    abstract public function longitudeToDecimal();
}