<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace arc;

/**
 *	Utility methods to handle common path related tasks, cleaning, changing relative to absolute, etc.
 */
class path
{
    protected static $collapseCache = array();

    /**
     *	This method returns all the parent paths for a given path, starting at the root and including the
     *	given path itself.
     *
     *	Usage:
     *		\arc\path::parents( '/foo/bar/doh/', '/foo/' ); // => [ '/foo/', '/foo/bar/', '/foo/bar/doh/' ]
     *
     *	@param string $path The path to derive all parent paths from.
     *	@param string $root The root or topmost parent to return. Defaults to '/'.
     *	@return array Array of all parent paths, starting at the root and ending with the given path.
     *		Note: It includes the given path!
     *		Note: when $path is not a child of $root, and empty array is returned
     */
    public static function parents($path, $root = '/')
    {
        $parents = [];
        if (self::isChild($path, $root)) {
            $subpath = substr($path, strlen($root));
            // returns all parents starting at the root, up to and including the path itself
            $prevpath = '';
            $parents = self::reduce( $subpath, function ($result, $entry) use ($root, &$prevpath) {
                $prevpath .= $entry . '/';
                $result[] = $root . $prevpath;

                return $result;
            }, [ $root ] );
        }

        return $parents;
    }

    /**
     *	This method parses a path which may contain '..' or '.' or '//' entries and returns the resulting
     *	absolute path.
     *
     *	Usage:
     *		\arc\path::collapse( '../', '/foo/bar/' ); // => '/foo/'
     *		\arc\path::collapse( '\\foo\\.\\bar/doh/../' ); // => '/foo/bar/'
     *
     *	@param string $path The input path, which may be relative. If this path starts with a '/' it is
     *		considered to start in the root.
     *	@param string $cwd The current working directory. For relative paths this is the starting point.
     *	@return string The absolute path, without '..', '.' or '//' entries.
     */
    public static function collapse($path, $cwd = null)
    {
        // removes '.', changes '//' to '/', changes '\\' to '/', calculates '..' up to '/'
        if ( $path instanceof \arc\path\Value ) {
            return $path;
        }
        if ( !isset($path[0]) ) {
            return $cwd;
        }
        if ( isset($cwd) && $cwd && $path[0] !== '/' && $path[0] !== '\\' ) {
            $path = $cwd . '/' . $path;
        }
        if ( isset(self::$collapseCache[$path]) ) { // cache hit - so return that
            return self::$collapseCache[$path];
        } else {
            $value = new \arc\path\Value($path);
            if ( isset(self::$collapseCache[(string)$value]) ) {
                self::$collapseCache[$path] = self::$collapseCache[(string)$value];
                return self::$collapseCache[(string)$value];
            } else {
                self::$collapseCache[$path] = self::$collapseCache[(string) $value] = $value;
                return $value;
            }
        }
    }

    /**
     *	This method cleans the input path with the given filter method. You can specify any of the
     *	sanitize methods valid for filter_var or you can use your own callback function. By default
     *	it url encodes each filename in the path.
     *
     *	Usage:
     *		\arc\path::clean( '/a path/to somewhere/' ); // => '/a%20path/to%20somewhere/'
     *
     *	@param string $path The path to clean.
     *	@param mixed $filter Either one of the sanitize filters for filter_var or a callback method as
     *		in \arc\path::map
     *	@param mixed $flags Optional list of flags for the sanitize filter.
     *	@return string The cleaned path.
     */
    public static function clean($path, $filter = null, $flags = null)
    {
        if (is_callable( $filter )) {
            $callback = $filter;
        } else {
            if (!isset( $filter )) {
                 $filter = FILTER_SANITIZE_ENCODED;
            }
            if (!isset($flags)) {
                $flags = FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH;
            }
            $callback = function ($entry) use ($filter, $flags) {
                return filter_var( $entry, $filter, $flags);
            };
        }

        return self::map( $path, $callback );
    }

    /**
     *	Returns either the immediate parent path for the given path, or null if it is outside the
     *	root path. Differs with dirname() in that it will not return '/' as a parent of '/', but
     *	null instead.
         *
     *	Usage:
     *		\arc\path::parent( '/foo/bar/' ); // => '/foo/'
     *
     *	@param string $path The path from which to get the parent path.
     *	@param string $root Optional root path, defaults to '/'
     *	@return string|null The parent of the given path or null if the parent is outside the root path.
     */
    public static function parent($path, $root = '/')
    {
        if ($path == $root) {
            return null;
        }
        $parent = dirname( $path );
        if (isset($parent[1])) { // fast check to see if there is a dirname
            $parent .= '/';
        }
        $parent[0] = '/'; // dirname('/something/') returns '\' in windows.
        if (strpos( (string)$parent, (string)$root ) !== 0) { // parent is outside of the root

            return null;
        }

        return $parent;
    }


    /**
     *  Returns the root entry of the given path.
     *
     *  Usage:
     *    $rootEntry = \arc\path::head( '/root/of/a/path/' ); // => 'root'
     *
     *  @param string $path The path to get the root entry of.
     *  @return string The root entry of the given path, without slashes.
     */
    public static function head($path)
    {
        if (!\arc\path::isAbsolute($path)) {
            $path = '/' . $path;
        }

        return substr( (string)$path, 1, strpos( (string)$path, '/', 1) - 1 );
    }

    /**
     *  Returns the path without its root entry.
     *
     *  Usage:
     *    $remainder = \arc\path::tail( '/root/of/a/path/' ); // => '/of/a/path/'
     *
     *  @param string $path The path to get the tail of.
     *  @return string The path without its root entry.
     */
    public static function tail($path)
    {
        if (!\arc\path::isAbsolute($path)) {
            $path = '/' . $path;
        }

        return substr( (string)$path, strpos( (string)$path, '/', 1) );
    }

    /**
     *  Returns the difference between sourcePath and targetPath as a relative path in
     *  such a way that if you append the relative path to the source path and collapse
     *  that, the result is the targetPath.
     *  @param string $targetPath The target path to map to.
     *  @param string $sourcePath The source path to start with.
     *  @return string The relative path from source to target.
     */
    public static function diff($sourcePath, $targetPath)
    {
        $diff = '';
        $targetPath = \arc\path::collapse( $targetPath );
        $sourcePath = \arc\path::collapse( $sourcePath );
        $commonParent = \arc\path::search( $sourcePath, function ($path) use ($targetPath, &$diff) {
            if (!\arc\path::isChild( $targetPath, $path )) {
                $diff .= '../';
            } else {
                return $path;
            }
        }, false);
        $diff .= substr( $targetPath, strlen( $commonParent ) );

        return $diff;
    }

    /**
     *  Returns true if the path is a child or descendant of the parent.
     *  @param string $path The path to check
     *  @param string $parent The parent to check.
     *  @return bool True if path is a child or descendant of parent
     */
    public static function isChild($path, $parent)
    {
        $parent = self::collapse($parent);
        $path   = self::collapse($path, $parent);
        return ( strpos( (string)$path, (string)$parent ) === 0 );
    }

    /**
     *  Returns true if the given path starts with a '/'.
     * @param  string $path The path to check
     * @return bool   True is the path starts with a '/'
     */
    public static function isAbsolute($path)
    {
        return isset($path[0]) && $path[0] === '/';
    }

    protected static function getSplitPath($path)
    {
        return preg_split('|/|', $path, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     *	Applies a callback function to each filename in a path. The result will be the new filename.
     *
     *	Usage:
     *		/arc/path::map( '/foo>bar/', function ($filename) {
     *			return htmlentities( $filename, ENT_QUOTES );
     *		} ); // => '/foo&gt;bar/'
     *
     *	@param string $path The path to alter.
     *	@param Callable $callback
     *	@return string A path with all filenames changed as by the callback method.
     */
    public static function map($path, $callback)
    {
        $splitPath = self::getSplitPath( $path );
        if (count($splitPath)) {
            $result = array_map( $callback, $splitPath );

            return '/' . implode( '/', $result ) .'/';
        } else {
            return '/';
        }
    }

    /**
     *	Applies a callback function to each filename in a path, but the result of the callback is fed back
     *	to the next call to the callback method as the first argument.
     *
     *	Usage:
     *		/arc/path::reduce( '/foo/bar/', function ($previousResult, $filename) {
     *			return $previousResult . $filename . '\\';
     *		}, '\\' ); // => '\\foo\\bar\\'
     *
     *	@param string $path The path to reduce.
     *	@param Callable $callback The method to apply to each filename of the path
     *	@param mixed $initial Optional. The initial reduced value to start the callback with.
     *	@return mixed The final result of the callback method
     */
    public static function reduce($path, $callback, $initial = null)
    {
        return array_reduce( self::getSplitPath( $path ), $callback, $initial );
    }

    /**
     *	Applies a callback function to each parent of a path, in order. Starting at the root by default,
     *	but optionally in reverse order. Will continue while the callback method returns null, otherwise
     *	returns the result of the callback method.
     *
     *	Usage:
     *		$result = \arc\path::search( '/foo/bar/', function ($parent) {
     *			if ($parent == '/foo/') { // silly test
     *				return true;
     *			}
     *		});
     *
     *	@param string $path Each parent of this path will be passed to the callback method.
     *	@param Callable $callback The method to apply to each parent
     *	@param bool $startAtRoot Optional. If set to false, root will be called last, otherwise first.
     *		Defaults to true.
     *	@param string $root Optional. Specify another root, no parents above the root will be called.
     *		Defaults to '/'.
     *	@return mixed The first non-null result of the callback method
     */
    public static function search($path, $callback, $startAtRoot = true, $root = '/')
    {
        $parents = self::parents( $path, $root );
        if (!$startAtRoot) {
            $parents = array_reverse( $parents );
        }
        foreach ($parents as $parent) {
            $result = call_user_func( $callback, $parent );
            if (isset( $result )) {
                return $result;
            }
        }
    }
}
