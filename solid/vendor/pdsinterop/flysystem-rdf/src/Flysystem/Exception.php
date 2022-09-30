<?php

namespace Pdsinterop\Rdf\Flysystem;

class Exception extends \League\Flysystem\Exception
{
    public static function create(string $error, array $context, \Exception $previous = null): Exception
    {
        return new static(vsprintf($error, $context), 0, $previous);
    }
}
