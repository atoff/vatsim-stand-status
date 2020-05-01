<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class NoStandDataException extends \Exception
{
    protected $message = "No stand data has been loaded!";
}