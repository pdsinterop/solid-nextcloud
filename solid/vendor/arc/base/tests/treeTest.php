<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    class TreeTest extends PHPUnit\Framework\TestCase
    {
        function testExpand()
        {
            $collapsedTree = array(
                '/a/b/c/' => 'Een c',
                '/a/' => 'Een a',
                '/d/e/' => 'Een e'
            );

            $expandedTree = \arc\tree::expand( $collapsedTree );
            $recollapsedTree = \arc\tree::collapse( $expandedTree );
            $this->assertEquals( $collapsedTree,  $recollapsedTree );
            //not a requirement: $this->assertFalse( $collapsedTree === $recollapsedTree );
        }

        function testRecurse()
        {
            $node = \arc\tree::expand();
            $root = $node;
            for ($i = 0; $i < 225; $i ++) {
                $node = $node->appendChild($i, $i);
            }
            $arr = \arc\tree::collapse( $root );
            $this->assertEquals(225, count($arr));
        }

        function testAppend()
        {
            $tree = \arc\tree::expand();
            $tree->childNodes['foo'] = 'bar';
            $tree->cd('/foo/')->appendChild('test', 'a test');
            $collapsed = \arc\tree::collapse( $tree );
            $this->assertEquals( array(
                '/foo/' => 'bar',
                '/foo/test/' => 'a test'
            ), $collapsed);
        }

        function testClone()
        {
            $tree = \arc\tree::expand();
            $tree->childNodes['foo'] = 'bar';
            $clone = clone $tree;
            $clone->childNodes['foo'] = 'foo';
            $this->assertNotEquals( $tree, $clone );
            $this->assertNotEquals( $tree->childNodes, $clone->childNodes );
            $foo1 = $tree->cd('/foo/');
            $foo2 = $clone->cd('/foo/');
            $this->assertNotEquals( $foo1, $foo2 );
            $this->assertEquals('bar', $tree->childNodes['foo'] );
            $this->assertEquals('foo', $clone->childNodes['foo'] );
        }

        function testCD()
        {
            $collapsed = array(
                '/foo/' => 'bar',
                '/foo/test/' => 'a test',
                '/bar/test/' => 'another test'
            );
            $tree = \arc\tree::expand($collapsed);
            $bar = $tree->cd('bar'); //childNodes['bar'];
            $this->assertEquals( 'a test', $bar->cd('/foo/test/') );
        }

        function testAutoCreateCD()
        {
            $tree = \arc\tree::expand();
            $tree->cd('foo')->nodeValue = 'bar';
            $collapsed = \arc\tree::collapse($tree);
            $this->assertEquals( array( '/foo/' => 'bar' ), $collapsed  );
        }


        function testRemove()
        {
            $tree = \arc\tree::expand();
            $tree->childNodes['foo'] = 'bar';
            $foo = $tree->childNodes['foo'];
            $tree->removeChild('foo');
            $this->assertNull($foo->parentNode);            
        }
    }
