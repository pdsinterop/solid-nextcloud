<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    class PathTest extends PHPUnit\Framework\TestCase
    {
        function testMapReduce()
        {
            $path = '/a/b/c/';
            $result = \arc\path::map( $path, function ($entry) {
                return strtoupper($entry);
            });
            $this->assertEquals( '/A/B/C/', $result);

            $result = \arc\path::reduce( $path, function ($result, $entry) {
                return $result.$entry;
            });
            $this->assertEquals( $result, 'abc' );

            $result = \arc\path::map( '/', function ($entry) {
                return 'a';
            });
            $this->assertEquals( '/', $result);

            $result = \arc\path::map( 'frop', function ($entry) {
                return 'a';
            });
            $this->assertEquals( '/a/', $result);
        }

        function testSearch()
        {
            $path = '/a/b/c/';
            $count = 0;
            $result = \arc\path::search( $path, function ($parent) use (&$count) {
                $count++;
                if ($parent == '/a/') {
                    return true;
                }
            });
            $this->assertTrue( $result );
            $this->assertEquals( 2, $count);

            $count = 0;
            $result = \arc\path::search( $path, function ($parent) use (&$count) {
                $count++;
                if ($parent == '/a/') {
                    return true;
                }
            }, false ); // reverse order
            $this->assertTrue( $result );
            $this->assertEquals( 3, $count);
        }

        function testCollapse()
        {
            $this->assertEquals( '/',      \arc\path::collapse('/'));
            $this->assertEquals( '/test/', \arc\path::collapse('/test/'));
            $this->assertEquals( '/test/', \arc\path::collapse('/test//'));
            $this->assertEquals( '/',      \arc\path::collapse('/test/../'));
            $this->assertEquals( '/test/', \arc\path::collapse('test'));
            $this->assertEquals( '/',      \arc\path::collapse( '../', '/test/'));
            $this->assertEquals( '/test/', \arc\path::collapse( '..', '/test/foo/'));
            $this->assertEquals( '/',      \arc\path::collapse( '/..//../', '/test/'));
            $this->assertEquals( '/test/', \arc\path::collapse( '', '/test/'));
        }

        function testParents()
        {
            $parents = \arc\path::parents('/test/');
            $this->assertEquals( $parents, array('/','/test/'));

            $parents = \arc\path::parents('/test/foo/','/test/');
            $this->assertEquals( $parents, array( '/test/', '/test/foo/'));

            $parents = \arc\path::parents('/test/','/tost/');
            $this->assertEquals( $parents, []);

            $parents = \arc\path::parents('/test/','/test/foo/');
            $this->assertEquals( $parents , []);
        }

        function testParent()
        {
            $this->assertNull( \arc\path::parent('/'));
            $this->assertEquals( '/', \arc\path::parent('/test/'));
            $this->assertEquals( '/a/', \arc\path::parent('/a/b/'));
            $this->assertNull( \arc\path::parent('/a/b/', '/a/b/'));
            $this->assertEquals( '/a/', \arc\path::parent('/a/b/', '/a/'));
            $this->assertNull( \arc\path::parent('/a/b/', '/test/'));
        }

        function testClean()
        {
            $this->assertEquals( '/a/b/', \arc\path::clean('/a/b/'));
            $this->assertEquals( '/%20/', \arc\path::clean(' '));
            $this->assertEquals( '/%23/', \arc\path::clean('/#/'));
            $this->assertEquals( '/n /', \arc\path::clean('/an a/', function ($filename) {
                return str_replace( 'a','', $filename );
            }));
        }

        function testIsChild()
        {
            $this->assertTrue( \arc\path::isChild( '/a/b/', '/a/' ) );
            $this->assertFalse( \arc\path::isChild( '/b/', '/a/' ) );
        }

        function testDiff()
        {
            $this->assertEquals( 'b/', \arc\path::diff( '/a/', '/a/b/' ));
            $this->assertEquals( '../b/', \arc\path::diff( '/a/', '/b/' ));
        }
    }
