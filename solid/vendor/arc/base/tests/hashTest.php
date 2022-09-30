<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the
     * LICENSE
     * file that was distributed with this source code.
     */

    class HashTest extends PHPUnit\Framework\TestCase
    {
        function testHashGet()
        {
            $hash = [
                'foo' => [
                    'bar' => 'This is a bar'
                ]
            ];
            $result = \arc\hash::get( '/foo/bar/', $hash );
            $this->assertEquals( $result, $hash['foo']['bar'] );

            $result = \arc\hash::get( '/foo/baz/', $hash );
            $this->assertNull( $result );

            $result = \arc\hash::get( '/foo/baz/', $hash, 'default' );
            $this->assertEquals( 'default', $result );

        }

        function testHashExists()
        {
            $hash = [
                'foo' => [
                    'bar' => 'This is a bar'
                ]
            ];
            $result = \arc\hash::exists( '/foo/bar/', $hash );
            $this->assertTrue( $result );
            $result = \arc\hash::exists( '/foo/baz/', $hash );
            $this->assertFalse( $result );
        }

        function testHashCompile()
        {
            $path = '/foo/bar/0/';
            $result = \arc\hash::compileName( $path );
            $this->assertEquals( 'foo[bar][0]', $result );
        }

        function testHashParse()
        {
            $name = 'foo[bar][0]';
            $result = \arc\hash::parseName( $name );
            $this->assertEquals( '/foo/bar/0/', $result );
        }

        function testHashWithSlash()
        {
            $name = 'foo[bar/baz]';
            $result = \arc\hash::parseName($name);
            $this->assertEquals( '/foo/bar%2Fbaz/', $result );
            $result = \arc\hash::compileName($result);
            $this->assertEquals( 'foo[bar/baz]', $result);
        }

        function testTree()
        {
            $hash = [
                'foo' => [
                    'bar' => 'This is a bar'
                ]
            ];
            $node = \arc\hash::tree( $hash );
            $tree = \arc\tree::collapse( $node );
            $this->assertEquals( [ '/foo/bar/' => 'This is a bar' ], $tree );
        }

    }
