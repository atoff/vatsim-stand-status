<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class UnableToLoadStandDataFileException extends \Exception
{
    protected $message = "The given stand data file was not able to be loaded";
}