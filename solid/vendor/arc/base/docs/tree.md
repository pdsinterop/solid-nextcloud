# arc/tree

This component provides a few methods to work on a filesystem like tree.
Except for expand all methods expect a tree node object as input. The tree node must have the following properties:

  - parentNode - a reference to a parentNode or null.
  - childNodes - an array with childnode objects.
  - nodeValue - any kind of value for the node.
  - nodeName - Optional. The unique name of the node among its siblings. Most methods allow an alternative name or even a closure that returns it given the node.

The expand method expects an array as input. It will return a tree built from this array. The key must be a path and the value will be set as nodeValue.

## \arc\tree::collapse
    (array) \arc\tree::collapse( (object) $node, (string) $root = '', (mixed) $nodeName = 'nodeName' );

Create a simple array from a tree, as [ $path => $nodeValue ]. Each non-null nodeValue will be added with the path to its node as the key. 
If $root is set, it will be prepended to the path of each entry in the array. The path is built from each node's name and its parents names.
If $nodeName is set, this property name will be used instead of 'nodeName'. If it is callable, it will be called with each node as only argument. The result will be used as the node name.

## \arc\tree::dive
    (mixed) \arc\tree::dive( (object) $node, (callable) $diveCallback = null, (callable) $riseCallback = null )

Calls $diveCallback -- if set -- on each successive parent until a non-null value is returned. Then calls all the parents from that point back to this node with $riseCallback -- if sest -- in reverse order. The first callback (dive) must accept one parameter, the node. The second callback (rise) must accept two parameters,  the node and the result up to that point.

## \arc\tree::expand
    (object) \arc\tree::expand( (array) $tree = null )

Creates a NamedNode tree from an array with path => nodeValue entries. If the input array is empty or null, a single root node is returned with a null nodeValue.

## \arc\tree::filter
    (array) \arc\tree::filter( (object) $node, (callable) $callback, (string) $root = '', (mixed) $nodeName = 'nodeName' )

Filters the tree using a callback method. If the callback method returns true, the node's value is included in the result, otherwise it is skipped. Filter returns a collapsed tree: [ path => nodeValue ]. The callback method must take one argument: the current node.

## \arc\tree::ls
    (array) \arc\tree::ls( (object) $node, (callable) $callback, (mixed) $nodeName = 'nodeName' )

Calls the callback method on each of the direct child nodes of the given node.

## \arc\tree::map
    (array) \arc\tree::map( (object) $node, (callable) $callback, (string) $root = '', (mixed) $nodeName = 'nodeName' )

Calls the callback method on each child of the current node, including the node itself. Any non-null result is added to the result array, with the path to the node as the key.

## \arc\tree::parents
    (mixed) \arc\tree::parents( (object) $node, (callable) $callback = null )

Calls the callback method on each parent of the given node, starting at the root. If no callback method is given, it will return an array with all parent nodes. This method will also include the node as a parent of itself.

## \arc\tree::reduce
    (mixed) \arc\tree::reduce( (object) $node, (callable) $callback, (mixed) $initial = null )

Calls the callback method on all child nodes of the given node, including the node itself. The result of each call is passed on as the first argument to each succesive call.

## \arc\tree::search
    (mixed) \arc\tree::search( (object) $node, (callable) $callback )

Calls the callback method on each child of the current node, including the node itself, until a non-null result is returned. Returns that result. The tree is searched depth first.

## \arc\tree::sort
    (array) \arc\tree::sort( (object) $node, (callable) $callback, (mixed) $nodeName = 'nodeName )

Sorts the childNodes list of the node, recursively.