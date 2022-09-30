<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    class TestHTTP extends PHPUnit\Framework\TestCase
    {

        function testGet()
        {
            $res = \arc\http::get( 'http://www.ariadne-cms.org/', '?foo=bar' );
            $this->assertNotEmpty($res);
        }

        function testClient()
        {
            $res = \arc\http::client();
            $this->assertInstanceOf('\arc\http\Client',$res);
        }


        function testHeaders()
        {
            $headerString = <<< EOF
HTTP/1.1 200 OK
Cache-Control: no-transform,public,max-age=300,s-maxage=900
Content-Type: text/html; charset=UTF-8
Date: Mon, 29 Apr 2013 16:38:15 GMT
ETag: "bbea5db7e1785119a7f94fdd504c546e"
Last-Modified: Sat, 27 Apr 2013 00:44:54 GMT
Server: AmazonS3
Vary: Accept-Encoding
X-Cache: HIT
X-Multiple: One
X-Multiple: Two
X-Multiple: Three
EOF;
            $headers = \arc\http\headers::parse($headerString);
            $this->assertEquals('AmazonS3', $headers['Server']);
            $this->assertEquals('HTTP/1.1 200 OK', $headers[0]);
            $this->assertEquals(['One','Two','Three'], $headers['X-Multiple']);

            $cachetime = \arc\http\headers::parseCacheTime($headers);
            $this->assertEquals(300, $cachetime);
        }

        function testCacheNoStore()
        {
            $headerString = <<< EOF
HTTP/1.1 200 OK
Cache-Control: no-store,public,max-age=300,s-maxage=900
EOF;
            $headers = \arc\http\headers::parse($headerString);
            $cachetime = \arc\http\headers::parseCacheTime($headers);
            $this->assertEquals(0, $cachetime);
        }

        function testCachePrivate()
        {
            $headerString = <<< EOF
HTTP/1.1 200 OK
Cache-Control: private,max-age=300,s-maxage=900
EOF;
            $headers = \arc\http\headers::parse($headerString);
            $cachetime = \arc\http\headers::parseCacheTime($headers, true);
            $this->assertEquals(300, $cachetime);

            $cachetime = \arc\http\headers::parseCacheTime($headers);
            $this->assertEquals(0, $cachetime);
        }

        function testCacheMultiple()
        {
            $headerString = <<< EOF
HTTP/1.1 200 OK
Cache-Control: public
Cache-Control: max-age=300,s-maxage=900
EOF;
            $headers = \arc\http\headers::parse($headerString);
            $cachetime = \arc\http\headers::parseCacheTime($headers);
            $this->assertEquals(300, $cachetime);
        }

        function testCacheMultiline()
        {
            $headerString = <<< EOF
HTTP/1.1 200 OK
Cache-Control: public,
    max-age=300,s-maxage=900
EOF;
            $headers = \arc\http\headers::parse($headerString);
            $cachetime = \arc\http\headers::parseCacheTime($headers);
            $this->assertEquals(300, $cachetime);
        }

        function testAcceptContent()
        {
            $header = 'Accept: text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, *'.'/'.'*;q=0.5';
            $accept = \arc\http\headers::parseHeader($header);
            $this->assertEquals('0.3', $accept[0]['q']);
            $this->assertEquals('1',$accept[2]['level']);
            $acceptable = ['text/plain','text/html'];
            $result = \arc\http\headers::accept($header, $acceptable);
            $this->assertEquals('text/html', $result);
        }
    }
