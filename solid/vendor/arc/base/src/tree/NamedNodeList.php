<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace arc\tree;

/**
 * Implements an ArrayObject with constraint that a value is always a NamedNode and its key is always the name
 * of that node
 * @property \arc\tree\NamedNode $parentNode
 */
class NamedNodeList extends \ArrayObject
{
    private $parentNode = null;

    public function __construct($list = null, $parentNode = null)
    {
        parent::__construct( $list );
        $this->parentNode = $parentNode;
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'parentNode':
                $this->parentNode = $value;
                foreach ($this as $child) {
                    $child->parentNode = $this->parentNode;
                }
                break;
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'parentNode':
                return $this->parentNode;
                break;
        }
    }

    public function offsetSet($name, $value)
    {
        if (!($value instanceof \arc\tree\NamedNode)) {
            $value = new \arc\tree\NamedNode( $name, $this->parentNode, null, $value );
        }
        if ($value->parentNode != $this->parentNode) {
            $value->parentNode = $this->parentNode;
        }
        if ($this->offsetExists( $name )) {
            $old = $this->offsetGet( $name );
            if ($old !== $value) {
                $old->parentNode = null;
            }
        }
        parent::offsetSet($name, $value);
    }

    public function offsetUnset($name)
    {
        if ($this->offsetExists( $name )) {
            $node = $this->offsetGet( $name );
            if ($node->parentNode) {
                $node->parentNode = null;
            }
        }
        parent::offsetUnset( $name );
    }

    public function __clone()
    {
        $this->parentNode = null;
        foreach ((array) $this as $name => $child) {
            $clone = clone $child;
            $clone->parentNode = $this->parentNode;
            parent::offsetSet( $name, $clone );
        }
    }

    public function exchangeArray($input)
    {
        $oldArray = $this->getArrayCopy();
        foreach ($oldArray as $node) {
            $node->parentNode = null; // removes them from the childNodes list as well
        }
        foreach ($input as $name => $node) {
            $this->offsetSet( $name, $node );
        }
    }
}
