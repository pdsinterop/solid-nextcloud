<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class TestHTTP_clientStream extends PHPUnit\Framework\TestCase
{
    function testCreateInstance()
    {
        $client = new \arc\http\ClientStream();
        $this->assertTrue( $client instanceof  \arc\http\ClientStream );

        $options = [
            'headers' => 'X-Test-Header: frop',
            'method' => 'HEAD'
        ];

        $client = new \arc\http\ClientStream($options);

        // do request, any will do, just that requestHeaders will get set
        $client->get('https://www.ariadne-cms.org/');

        $this->assertTrue(in_array("X-Test-Header: frop",$client->requestHeaders));
    }

    function testGet()
    {
        $client = new \arc\http\ClientStream();
        $res = $client->get('https://www.ariadne-cms.org/');

        $this->assertTrue( $res != '');
        $this->assertTrue ($client->responseHeaders[0] == 'HTTP/1.1 200 OK');
    }

    function testHeader()
    {
        $client = new \arc\http\ClientStream();
        $client->headers(['User-Agent: SimpleTestClient']);

        // set second set of headers as string
        $client->headers("X-Debug1: false\r\nX-Debug2: true\r\n");

        // do request, any will do
        $client->get('https://www.ariadne-cms.org/');

        $this->assertTrue(in_array("User-Agent: SimpleTestClient",$client->requestHeaders));
        // should not contain an empty line
        $this->assertFalse(strstr(join("\r\n",$client->requestHeaders),"\r\n\r\n") !== false);
    }

    function testBroken()
    {
        $this->expectException(\arc\IllegalRequest::class);
        $client = new \arc\http\ClientStream();
        $page = $client->get('afeafawfafweaga');
    }

    // second request should unset old data
    function testSecondRequest()
    {
        $client = new \arc\http\ClientStream();
        $res1 = $client->get('https://www.ariadne-cms.org/');
        $resHeader1 = $client->responseHeaders;

        $res2 = $client->get('https://www.muze.nl/');
        $resHeader2 = $client->responseHeaders;
        $this->assertTrue($resHeader1 !== $resHeader2);
    }

    function testFailGet()
    {
        $client = new \arc\http\ClientStream();

        // do request, any will do
        $result = $client->get('http://broken/');

        $this->assertFalse($result);
    }

    function testParseHeaders() {
        $client = new \arc\http\ClientStream();
        $res = $client->get('https://www.ariadne-cms.org/');
        $headers = \arc\http\headers::parse( $client->responseHeaders );
        $this->assertTrue( $headers[0] == 'HTTP/1.1 200 OK');
        $this->assertTrue( isset($headers['Content-Type']));
    }

    function testParseCacheTime() {
        $client = new \arc\http\ClientStream();
        $headers = [
            'Expires: '.gmdate('D, d M Y H:i:s T', time()+100)
        ];
        date_default_timezone_set("UTC");
        $cachetime = \arc\http\headers::parseCacheTime( $headers );
        $this->assertTrue( $cachetime > 0 );
    }
}
