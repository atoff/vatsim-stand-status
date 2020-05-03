<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class InvalidCoordinateFormat extends \Exception
{
    protected $message = "The current coordinate format is invalid for the operation requested";
}