<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class CoordinateOutOfBoundsException extends \Exception
{
    protected $message = "The given coordinates are out of bounds";
}