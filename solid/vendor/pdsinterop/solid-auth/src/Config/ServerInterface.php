<?php

namespace Pdsinterop\Solid\Auth\Config;

use Pdsinterop\Solid\Auth\Exception\LogicException;

interface ServerInterface extends \JsonSerializable
{
    public function get($key);

    public function getRequired(): array;

    public function __toString(): string;

    /**
     * @return array
     *
     * @throws LogicException for missing required properties
     */
    public function jsonSerialize(): array;

    public function validate(): bool;
}
