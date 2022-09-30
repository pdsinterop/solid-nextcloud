<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */


    class TestUrlQuery extends PHPUnit\Framework\TestCase
    {
        function testparsePHPUrl()
        {
            $type = ini_get('arg_separator.input');

            $starturl = 'http://www.ariadne-cms.org/?frop=1;frml=2&frup=3';
            $url = \arc\url::Url($starturl);
            $query = $url->query;

            switch ($type) {
                case '&':
                    $this->assertTrue ( isset($query['frop']) );
                    $this->assertFalse( isset($query['frml']) );
                    $this->assertTrue ( isset($query['frup']) );
                    $this->assertFalse( $query['frop'] == '1' );
                    $this->assertTrue ( $query['frup'] == '3' );
                    $this->assertTrue ( $query['frop'] == '1;frml=2' );
                    break;
                case ';&':
                case '&;':
                    $this->assertTrue ( isset($query['frop']) );
                    $this->assertTrue ( isset($query['frml']) );
                    $this->assertTrue ( isset($query['frup']) );
                    $this->assertTrue ( $query['frop'] == '1' );
                    $this->assertTrue ( $query['frml'] == '2' );
                    $this->assertTrue ( $query['frup'] == '3' );
                    $this->assertFalse( $query['frop'] == '1;frml=2' );
                    $this->assertFalse( $query['frml'] == '2;frup=3' );
                    break;
                case ';':
                    $this->assertTrue ( isset($query['frop']) );
                    $this->assertTrue ( isset($query['frml']) );
                    $this->assertFalse( isset($query['frup']) );
                    $this->assertTrue ( $query['frop'] == '1' );
                    $this->assertFalse( $query['frml'] == '2' );
                    $this->assertFalse( $query['frop'] == '1;frml=2' );
                    $this->assertTrue ( $query['frml'] == '2&frup=3' );
                break;
                default:
                    $this->assertTrue ( false ); // always fail when we don't have any test cases
            }

        }

        function testparseSafeUrl()
        {
            $starturl = 'http://www.ariadne-cms.org/?frop=1;frml=2&frup=3';
            $url = \arc\url::safeUrl($starturl);
            $query = $url->query;

            $this->assertTrue ( isset($query['frop']) );
            $this->assertTrue ( isset($query['frml']) );
            $this->assertTrue ( isset($query['frup']) );
            $this->assertTrue ( $query['frop'] == '1' );
            $this->assertTrue ( $query['frml'] == '2' );
            $this->assertTrue ( $query['frup'] == '3' );
            $this->assertFalse( $query['frop'] == '1;frml=2' );
            $this->assertFalse( $query['frml'] == '2;frup=3' );
        }

        function testArrayAccess()
        {
            $starturl = 'http://www.ariadne-cms.org/';
            $url = \arc\url::url($starturl);
            $query = $url->query;
            $query->foo = ['bar' => 'baz'];
            $this->assertEquals( 'baz', $query->foo['bar'] );

            $query->foo['bar'] = 'zab';
            $this->assertEquals( 'zab', $query->foo['bar'] );

            $query->foo['baz'] = 'bar';
            $this->assertEquals( 'bar', $query->foo['baz'] );

            unset($query->foo['baz']);
            $this->assertArrayNotHasKey('baz', $query->foo);
        }

        function testArrayToString()
        {
            $starturl = 'http://www.ariadne-cms.org/';
            $url = \arc\url::url($starturl);
            $query = $url->query;
            $query->foo = ['bar' => 'baz'];
            $query->foo['baz'] = 'bar';
            $str = (string)$url;
            $this->assertEquals('http://www.ariadne-cms.org/?foo%5Bbar%5D=baz&foo%5Bbaz%5D=bar', $str);
        }
    }
