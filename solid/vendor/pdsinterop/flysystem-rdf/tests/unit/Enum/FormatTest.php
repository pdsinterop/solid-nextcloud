<?php

namespace Pdsinterop\Rdf\Enum;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdsinterop\Rdf\Enum\Format
 */
class FormatTest extends TestCase
{
    /**
     * @testdox Enum should return `true` when asked if it has a supported format
     *
     * @covers ::has
     *
     * @uses \Pdsinterop\Rdf\Enum\Format::keys
     *
     * @dataProvider provideFormats
     */
    public function testHas($format): void
    {
        $actual = Format::has($format);

        $this->assertTrue($actual);
    }

    /**
     * @testdox Enum should return `false` when asked if it has a `guess` format
     *
     * @covers ::has
     *
     * @uses \Pdsinterop\Rdf\Enum\Format::keys
     */
    public function testHasForGuess(): void
    {
        $actual = Format::has('guess');

        $this->assertFalse($actual);
    }

    /**
     * @testdox Enum should return supported formats when asked for all is keys
     *
     * @covers ::keys
     */
    public function testKeys(): void
    {
        $actual = Format::keys();

        $expected = [
            'JSON_LD' => 'jsonld',
            'N_TRIPLES' => 'ntriples',
            'NOTATION_3' => 'n3',
            'RDF_XML' => 'rdfxml',
            'TURTLE' => 'turtle',
        ];

        $this->assertEquals($expected, $actual);
    }

    public function provideFormats(): array
    {
        return [
            'jsonld' => ['jsonld'],
            'ntriples' => ['ntriples'],
            'n3' => ['n3'],
            'rdfxml' => ['rdfxml'],
            'turtle' => ['turtle'],
        ];
    }
}
