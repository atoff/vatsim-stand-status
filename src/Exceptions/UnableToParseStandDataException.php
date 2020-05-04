<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class UnableToParseStandDataException extends \Exception
{
    protected $message = "The given stand data file was not able to be parsed";
}