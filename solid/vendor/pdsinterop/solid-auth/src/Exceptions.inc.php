<?php

namespace Pdsinterop\Solid\Auth\Exception;

use Throwable;

abstract class Exception extends \Exception implements \JsonSerializable
{
    final public function jsonSerialize(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'name' => static::class,
            // Development environment only
            //'file' => $this->getFile(),
            //'line' => $this->getLine(),
            //'trace' => $this->getTrace(),
        ];
    }
}

class LogicException extends Exception {}

class AuthorizationHeaderException extends Exception {}

class InvalidTokenException extends Exception {}
