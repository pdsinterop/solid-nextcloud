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
 * Implements a tree node which has uniquely named child nodes, as in a filesystem.
 * @property \arc\tree\NamedNodeList $childNodes
 * @property \arc\tree\NamedNode $parentNode
 * @property string $nodeName
 */
class NamedNode extends Node implements \Serializable
{
    public $nodeValue = null;
    private $parentNode = null;
    private $childNodes = null;
    private $nodeName = '';

    public function __construct($nodeName='', $parentNode = null, $childNodes = null, $nodeValue = null)
    {
        $this->nodeName = $nodeName;
        $this->parentNode = $parentNode;
        $this->childNodes = new NamedNodeList( (array) $childNodes, $this );
        $this->nodeValue = $nodeValue;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'nodeName':
                return $this->nodeName;
                break;
            case 'childNodes':
                return $this->childNodes;
                break;
            case 'parentNode':
                return $this->parentNode;
                break;
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'nodeName':
                $this->_setNodeName( $value );
                break;
            case 'childNodes':
                // make sure nodelists aren't shared between namednodes.
                $this->childNodes = new NamedNodeList( (array) $value, $this );
                break;
            case 'parentNode':
                $this->_setParentNode( $value );
                break;
        }
    }

    private function _setNodeName($name)
    {
        if ($this->parentNode) {
            if ($this->parentNode->childNodes[$name] !== $this) {
                $this->parentNode->childNodes[$name] = $this;
            }
        }
        $this->nodeName = $name;
    }

    private function _setParentNode($node)
    {
        if ($node instanceof NamedNode) {
            $node->appendChild( $this->nodeName, $this );
        } elseif (isset($node)) {
            throw new \arc\UnknownError( 'parentNode is not a \arc\tree\NamedNode', \arc\exceptions::ILLEGAL_ARGUMENT );
        } elseif ($this->parentNode) {
            $this->parentNode->removeChild( $this->nodeName );
        }
    }

    public function __isset($name)
    {
        switch ($name) {
            case 'nodeName':
            case 'childNodes':
                return true; // these are always _set_, but may be empty
                break;
            case 'parentNode':
                return isset( $this->parentNode );
                break;
            default:
                return isset( $this->childNodes[ $name ] );
                break;
        }
    }

    /* The tree itself must always be deep cloned, a single node cannot have two parentNodes.
     * The nodeValue may be whatever - so if it is an object, that object will not be cloned.
     */
    public function __clone()
    {
        $this->parentNode = null;
        $this->childNodes = clone $this->childNodes;
        $this->childNodes->parentNode = $this;
    }

    public function __toString()
    {
        return (string) $this->nodeValue;
    }

    // \Serializable interface
    public function serialize()
    {
        return serialize( \arc\tree::collapse( $this ) );
    }

    public function unserialize($data)
    {
        return \arc\tree::expand( unserialize( $data ) );
    }

    /**
     *	Adds a new child element to this node with the given name as index in the child list.
     *	If an existing child has the same name, that child will be discarded.
     *	@param string $name The index name of the child
     *	@param mixed $data The data for the new child. If $data is not an instance of \arc\tree\NamedNode
     *		a new instance will be constructed with $data as its nodeValue.
     *	@return \arc\tree\NamedNode The new child node.
     */
    public function appendChild($nodeName, $child=null)
    {
        if ( !( $child instanceof \arc\tree\NamedNode )) {
            $child = new \arc\tree\NamedNode( $nodeName, $this, null, $child );
        }
        if ( $child->parentNode !== $this) {
            if ( isset($child->parentNode)) {
                $child->parentNode->removeChild( $child->nodeName );
            }
            if (isset( $this->childNodes[ $nodeName ] )) {
                $oldChild = $this->childNodes[ $nodeName ];
                $oldChild->parentNode = null;
            }
            $child->parentNode = $this;
        }
        $this->childNodes[ $nodeName ] = $child;

        return $child;
    }

    /**
     *	Removes an existing child with the given name from this node.
     *	@param string $nodeName The index name of the child
     *	@return \arc\tree\NamedNode The removed child or null.
     */
    public function removeChild($nodeName)
    {
        if ( isset( $this->childNodes[ $nodeName ] )) {
            $child = $this->childNodes[ $nodeName ];
            $child->parentNode = null;
            unset( $this->childNodes[ $nodeName ] );

            return $child;
        } else {
            return null;
        }
    }

    /**
     * Returns the absolute path of the node.
     * @return string the absolute path of the node
     */
    public function getPath()
    {
        return \arc\tree::parents(
            $this,
            function ($node, $result) {
                return $result . $node->nodeName . '/';
            }
        );
    }

    /**
     * Returns the root node object of this tree.
     * @return \arc\tree\Node the root node
     */
    public function getRootNode()
    {
        $result = \arc\tree::dive(
            $this,
            function ($node) {
                return isset( $node->parentNode ) ? null : $node;
            }
        );

        return $result;
    }

    /**
     *	Returns the node with the given path, relative to this node. If the path
     *  does not exist, missing nodes will be created automatically.
     *	@param string $path The path to change to
     *	@return \arc\tree\NamedNode The target node corresponding with the given path.
     */
    public function cd($path)
    {
        if (\arc\path::isAbsolute( $path )) {
            $node = $this->getRootNode();
        } else {
            $node = $this;
        }
        $result = \arc\path::reduce( $path, function ($node, $name) {
            switch ($name) {
                case '..':
                    return ( isset( $node->parentNode ) ? $node->parentNode : $node );
                    break;
                case '.':
                case '':
                    return $node;
                    break;
                default:
                    if ( !isset( $node->childNodes[ $name ] ) ) {
                        return $node->appendChild( $name );
                    } else {
                        return $node->childNodes[ $name ];
                    }
                    break;
            }
        }, $node);

        return $result;
    }

    /**
     *  Calls a callback method on each child of this node, returns an array with name => result pairs.
     *  The callback method must accept two parameters, the name of the child and the child node itself.
     *  @param callable $callback The callback method to run on each child.
     *  @return array An array of result values with the name of each child as key.
     */
    public function ls($callback)
    {
        return \arc\tree::ls( $this, $callback );
    }

}
