<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.

     */

    class TestRoute extends PHPUnit\Framework\TestCase
    {
        function testMatch() {
            $config = [
                '/blog/' => function($path) {
                        if ( preg_match( '/(?<id>\d+)(?<format>..+)?/', $path, $params ) ) {
                            return $params;
                        } else {
                            return 'main';
                        }
                    },
                '/' => function($path) {
                        if ( $path ) {
                            return 'notfound';
                        }
                    }
            ];
            $result = \arc\route::match('/blog/42.html', $config);
            $this->assertEquals( $result['result']['id'], '42' );
            $this->assertEquals( $result['result']['format'], '.html' );
            $result = \arc\route::match('/not/here/', $config);
            $this->assertEquals( $result['result'], 'notfound');
        }

        function testNestedMatch()
        {
            $config = [
                '/site/' => function ($remainder) {
                    if (preg_match('|(?<name>[^/]+)(?<rest>.+)?|', $remainder, $params)) {
                        $subroute = [
                            '/blog/' => function ($remainder) use ($params) {
                                return 'Blog of ' . $params['name'];
                            },
                            '/'      => function ($remainder) use ($params) {
                                return 'Site of ' . $params['name'];
                            }
                        ];
                        return \arc\route::match($params['rest'], $subroute)['result'];
                    } else {
                        return 'main';
                    }
                },
                '/'      => function ($remainder) {
                    if ($remainder) {
                        return 'notfound';
                    } else {
                        return 'home';
                    }
                }
            ];
            $result = \arc\route::match('/site/mike/blog/', $config);
            $this->assertEquals( $result['result'], 'Blog of mike');
        }
    }