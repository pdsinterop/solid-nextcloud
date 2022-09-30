<?php

namespace arc\traits;

trait Proxy
{
    protected $target;

    public function __construct($target)
    {
        $this->target = $target;
    }

    public function __get($name)
    {
        return $this->target->$name;
    }

    public function __set($name, $value)
    {
        $this->target->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset( $this->target->$name );
    }

    public function __clone()
    {
        if (is_object( $this->target )) {
            $this->target = clone $this->target;
        }
    }

    public function __call($name, $args)
    {
        return call_user_func_array( [ $this->target, $name ], $args );
    }

    public function __toString()
    {
        return (string) $this->target;
    }
}
