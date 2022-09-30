<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGParserIterator;

class TriGParserIteratorTest extends TestCase
{
    public function testStream(): void
    {
        $input = fopen('php://memory', 'w');
        fwrite($input, <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN
        );
        fseek($input, 0);
        $parser = new TriGParserIterator();
        $iterator = $parser->parseStream($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);
        fclose($input);
    }

    public function testString(): void
    {
        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN;
        $parser = new TriGParserIterator();
        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);
    }

    public function testRepeat(): void
    {
        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN;
        $parser = new TriGParserIterator();

        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);

        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
<http://foo/bar> <http://bar/baz> _:genid1 .
IN;
        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(3, $values);
    }
}
