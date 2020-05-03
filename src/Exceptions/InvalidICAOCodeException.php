<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class InvalidICAOCodeException extends \Exception
{
    protected $message = "An invalid ICAO code was supplied";
}