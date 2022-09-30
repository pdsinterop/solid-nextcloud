<?php

namespace MJRider\FlysystemFactory;

use PHPUnit\Framework\TestCase;

class EndpointTest extends TestCase
{
    use Endpoint;

    /**
     * @dataProvider endpointProvider
     */
    public function testEndpoint($endpoint, $expected = null)
    {
        if (!isset($expected)) {
            $expected = $endpoint;
        }
        $url = self::endpointToURL($endpoint);
        $this->assertEquals($expected, $url);
    }

    public function endpointProvider()
    {
        return [
            ['example.com', 'https://example.com'],
            ['example.com/v1', 'https://example.com/v1'],
            ['example.com:1443/v1', 'https://example.com:1443/v1'],
            ['//example.com/v1', 'https://example.com/v1'],
            ['http://example.com:8080/v1']
        ];
    }
}
