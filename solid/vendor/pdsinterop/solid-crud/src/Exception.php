<?php

namespace Pdsinterop\Solid\Resources;

class Exception extends \Exception
{
    public static function create(string $error, array $context, \Exception $previous = null): Exception
    {
        return new self(vsprintf($error, $context), 0, $previous);
    }
}
