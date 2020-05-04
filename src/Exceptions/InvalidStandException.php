<?php

namespace CobaltGrid\VatsimStandStatus\Exceptions;

class InvalidStandException extends \Exception
{
    protected $message = "The data passed to the Stand object constructor was invalid";
}