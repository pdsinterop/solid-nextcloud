<?php

namespace Pdsinterop\Rdf;

use Pdsinterop\Rdf\Enum\Format;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdsinterop\Rdf\Formats
 *
 * @covers ::<!public>
 *
 * @uses \Pdsinterop\Rdf\Enum\Format
 */
class FormatsTest extends TestCase
{
    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::getAllExtensions
     */
    public function testGetAllExtensions(): void
    {
        $expected = [
            'jsonld' => ['jsonld', 'json'],
            'n3' => ['n3'],
            'ntriples' => ['nt'],
            'rdfxml' => ['rdf', 'xrdf', 'html'],
            'turtle' => ['ttl'],
        ];

        $formats = new Formats();
        $actual =  $formats->getAllExtensions();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getAllMimeTypes
     */
    public function testGetAllMimeTypes(): void
    {
        $expected = [
            'jsonld' => ['application/ld+json'],
            'n3' => ['text/n3', 'text/rdf+n3'],
            'ntriples' => [
                'application/n-triples',
                'text/plain',
                'text/ntriples',
                'application/ntriples',
                'application/x-ntriples',
            ],
            'rdfxml' => ['application/rdf+xml'],
            'turtle' => ['text/turtle', 'application/turtle', 'application/x-turtle'],
        ];

        $formats = new Formats();

        $actual =  $formats->getAllMimeTypes();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getExtensionsForFormat
     *
     * @uses \Pdsinterop\Rdf\Formats::getAllExtensions
     *
     * @dataProvider provideExtensionsForFormat
     */
    public function testGetExtensionsForFormat($format, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getExtensionsForFormat($format);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getFormatForExtension
     *
     * @dataProvider provideFormatForExtension
     *
     * @uses \Pdsinterop\Rdf\Formats::getAllExtensions
     */
    public function testGetFormatForExtension($extension, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getFormatForExtension($extension);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getFormatForMime
     *
     * @dataProvider provideFormatForMime
     *
     * @uses \Pdsinterop\Rdf\Formats::getAllMimeTypes
     */
    public function testGetFormatForMime($mime, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getFormatForMime($mime);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMimeForExtension
     *
     * @uses \Pdsinterop\Rdf\Formats
     *
     * @dataProvider provideMimeForExtension
     */
    public function testGetMimeForExtension($extension, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getMimeForExtension($extension);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMimeForFormat
     *
     * @uses \Pdsinterop\Rdf\Formats::getAllMimeTypes
     * @uses \Pdsinterop\Rdf\Formats::getMimesForFormat
     *
     * @dataProvider provideMimeForFormat
     */
    public function testGetMimeForFormat($format, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getMimeForFormat($format);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMimesForFormat
     *
     * @dataProvider provideMimesForFormat
     *
     * @uses \Pdsinterop\Rdf\Formats::getAllMimeTypes
     */
    public function testGetMimesForFormat($format, $expected): void
    {
        $formats = new Formats();

        $actual =  $formats->getMimesForFormat($format);

        $this->assertEquals($expected, $actual);
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function provideExtensionsForFormat(): array
    {
        return [
            'jsonld' => ['jsonld', ['jsonld', 'json']],
            'n3' => ['n3', ['n3']],
            'ntriples' => ['ntriples', ['nt']],
            'rdfxml' => ['rdfxml', ['rdf', 'xrdf', 'html']],
            'turtle' => ['turtle', ['ttl']],
        ];
    }

    public function provideFormatForExtension(): array
    {
        return [
            'jsonld (with leading dot)' => ['.jsonld', 'jsonld'],
            'jsonld' => ['jsonld', 'jsonld'],
            'jsonld:json (with leading dot)' => ['.json', 'jsonld'],
            'jsonld:json' => ['json', 'jsonld'],
            'n3 (with leading dot)' => ['.n3', 'n3'],
            'n3' => ['n3', 'n3'],
            'ntriples (with leading dot)' => ['.nt', 'ntriples'],
            'ntriples' => ['nt', 'ntriples'],
            'rdfxml:html (with leading dot)' => ['.html', 'rdfxml'],
            'rdfxml:html' => ['html', 'rdfxml'],
            'rdfxml:rdf (with leading dot)' => ['.rdf', 'rdfxml'],
            'rdfxml:rdf' => ['rdf', 'rdfxml'],
            'rdfxml:xrdf (with leading dot)' => ['.xrdf', 'rdfxml'],
            'rdfxml:xrdf' => ['xrdf', 'rdfxml'],
            'turtle (with leading dot)' => ['.ttl', 'turtle'],
            'turtle' => ['ttl', 'turtle'],
        ];
    }

    public function provideFormatForMime(): array
    {
        return [
            'jsonld:1' => ['application/ld+json', 'jsonld'],
            'n3:1' => ['text/n3', 'n3'],
            'n3:2' => ['text/rdf+n3', 'n3'],
            'ntriples:1' => ['application/n-triples', 'ntriples'],
            'ntriples:2' => ['text/plain', 'ntriples'],
            'ntriples:3' => ['text/ntriples', 'ntriples'],
            'ntriples:4' => ['application/ntriples', 'ntriples'],
            'ntriples:5' => ['application/x-ntriples', 'ntriples'],
            'rdfxml:1' => ['application/rdf+xml', 'rdfxml'],
            'turtle:1' => ['text/turtle', 'turtle'],
            'turtle:2' => ['application/turtle', 'turtle'],
            'turtle:3' => ['application/x-turtle', 'turtle'],
        ];
    }

    public function provideMimeForExtension(): array
    {
        return [
            'jsonld (with leading dot)' => ['.jsonld', 'application/ld+json'],
            'jsonld' => ['jsonld', 'application/ld+json'],
            'jsonld:json (with leading dot)' => ['.json', 'application/ld+json'],
            'jsonld:json' => ['json', 'application/ld+json'],
            'n3 (with leading dot)' => ['.n3', 'text/n3'],
            'n3' => ['n3', 'text/n3'],
            'ntriples (with leading dot)' => ['.nt', 'application/n-triples'],
            'ntriples' => ['nt', 'application/n-triples'],
            'rdfxml:html (with leading dot)' => ['.html', 'application/rdf+xml'],
            'rdfxml:html' => ['html', 'application/rdf+xml'],
            'rdfxml:rdf (with leading dot)' => ['.rdf', 'application/rdf+xml'],
            'rdfxml:rdf' => ['rdf', 'application/rdf+xml'],
            'rdfxml:xrdf (with leading dot)' => ['.xrdf', 'application/rdf+xml'],
            'rdfxml:xrdf' => ['xrdf', 'application/rdf+xml'],
            'turtle (with leading dot)' => ['.ttl', 'text/turtle'],
            'turtle' => ['ttl', 'text/turtle'],
        ];
    }

    public function provideMimeForFormat(): array
    {
        return [
            'jsonld' =>['jsonld', 'application/ld+json'],
            'n3' =>['n3', 'text/n3'],
            'ntriples' =>['ntriples', 'application/n-triples'],
            'rdfxml' => ['rdfxml', 'application/rdf+xml'],
            'turtle' =>['turtle', 'text/turtle'],
        ];
    }

    public function provideMimesForFormat(): array
    {
        return [
            'jsonld' =>['jsonld', ['application/ld+json']],
            'n3' =>['n3', ['text/n3', 'text/rdf+n3']],
            'ntriples' =>['ntriples', ['application/n-triples', 'text/plain', 'text/ntriples', 'application/ntriples', 'application/x-ntriples']],
            'rdfxml' => ['rdfxml', ['application/rdf+xml']],
            'turtle' =>['turtle', ['text/turtle', 'application/turtle', 'application/x-turtle']],

        ];
    }
}
