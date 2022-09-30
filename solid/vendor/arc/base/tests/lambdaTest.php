<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    class LambdaTest extends PHPUnit\Framework\TestCase
    {

        function testSingleton()
        {
            $bar = \arc\lambda::singleton( function () {
                return 'bar' . time();
            } );
            $baz = \arc\lambda::singleton( function () {
                return 'baz';
            } );
            $test1 = $bar();
            sleep(1);
            $test2 = $bar();
            $this->assertEquals( $test1, $test2 );
            $this->assertEquals( $baz(), 'baz' );
        }

        function testPartial()
        {
            $bar = function ($x, $y, $z, $q=1) {
                return [ 'x' => $x, 'y' => $y, 'z' => $z, 'q' => $q];
            };
            $baz = \arc\lambda::partial( $bar, [ 0 => 'x', 2 => 'z' ] );
            $result = $baz( 'y' );
            $this->assertEquals( $result, [ 'x' => 'x', 'y' => 'y', 'z' => 'z', 'q' => 1 ] );
        }

        function testPartialPartial()
        {
            $bar = function ($x, $y, $z='z', $q=1) {
                return [ 'x' => $x, 'y' => $y, 'z' => $z, 'q' => $q];
            };
            $baz = \arc\lambda::partial( $bar, [ 0 => 'x', 3 => 'q' ], [ 2 => 'z' ] );
            $result = $baz( 'y' );
            $this->assertEquals( $result, [ 'x' => 'x', 'y' => 'y', 'z' => 'z', 'q' => 'q' ] );
        }

        function testPepper()
        {
            $f = function($peppered, $reallypeppered) {
                return isset($peppered) && isset($reallypeppered) && $peppered==$reallypeppered;
            };
            $p = \arc\lambda::pepper( $f, [ 'peppered' => null, 'reallypeppered' => null] );
            $result = $p(['reallypeppered' => 1, 'peppered' => 1]);
            $this->assertTrue($result);
        }

    }
