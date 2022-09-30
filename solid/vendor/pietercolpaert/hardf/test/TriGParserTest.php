<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGParser;

class TriGParserTest extends TestCase
{
    /**
     * @param string|array<int,string> $input
     */
    private function shouldParse($createParser, $input = ''): void
    {
        $expected = \array_slice(\func_get_args(), 1);
        // Shift parameters as necessary
        if (\is_callable($createParser)) {
            array_shift($expected);
        } else {
            $input = $createParser;
            $createParser = function () {
                return new TriGParser();
            };
        }
        $results = [];
        $items = array_map(function ($item) {
            return ['subject' => $item[0], 'predicate' => $item[1], 'object' => $item[2], 'graph' => isset($item[3]) ? $item[3] : ''];
        }, $expected);
        $parser = $createParser();
        $parser->_resetBlankNodeIds();
        $parser->parse($input, function ($error, $triple = null) use (&$results, &$items) {
            //expect($error).not.to.exist;
            if ($triple) {
                $results[] = $triple;
            } else {
                $this->assertEquals(self::toSortedJSON($items), self::toSortedJSON($results));
            }
        });
    }

    /**
     * @param string      $input
     * @param string|null $expectedError
     */
    public function shouldNotParse($createParser, $input, $expectedError = null): void
    {
        $expected = \array_slice(\func_get_args(), 1);
        // Shift parameters as necessary
        if (!\is_callable($createParser)) {
            $expectedError = $input;
            $input = $createParser;
            $createParser = function () {
                return new TriGParser();
            };
        }
        $parser = $createParser();
        $parser->_resetBlankNodeIds();
        //hackish way so we only act upon first error
        $errorReceived = false;
        $parser->parse($input, function ($error, $triple = null) use ($expectedError, &$errorReceived) {
            //expect($error).not.to.exist;
            if (isset($error) && !$errorReceived) {
                $errorReceived = true;
                $this->assertEquals($expectedError, $error->getMessage());
            } elseif (!isset($triple) && !$errorReceived) {
                $errorReceived = true;
                $this->fail("Expected this error to be thrown (but it wasn't): ".$expectedError);
            }
        });
    }

    /**
     * @param string $baseIri
     * @param string $relativeIri
     * @param string $expected
     */
    public function itShouldResolve($baseIri, $relativeIri, $expected): void
    {
        $done = false;
        try {
            $doc = '<urn:ex:s> <urn:ex:p> <'.$relativeIri.'>.';
            $parser = new TriGParser(['documentIRI' => $baseIri]);
            $parser->parse($doc, function ($error, $triple) use (&$done, &$expected) {
                if (!$done && $triple) {
                    $this->assertEquals($expected, $triple['object']);
                }
                if (isset($error)) {
                    $this->fail($error);
                }
                $done = true;
            });
        } catch (\Exception $error) {
            $this->fail("Resolving <$relativeIri> against <$baseIri>.\nError message: ".$error->getMessage());
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    private static function toSortedJSON(array $items): string
    {
        $triples = array_map('json_encode', $items);
        sort($triples);

        return "[\n  ".implode("\n  ", $triples)."\n]";
    }

    public function testZeroOrMoreTriples(): void
    {
        // should parse the empty string
        $this->shouldParse(''
        /* no triples */);

        // should parse a whitespace string
        $this->shouldParse(" \t \n  "
        /* no triples */);

        // should parse a single triple
        $this->shouldParse('<a> <b> <c>.',
        ['a', 'b', 'c']);

        // should parse three triples
        $this->shouldParse("<a> <b> <c>.\n<d> <e> <f>.\n<g> <h> <i>.",
        ['a', 'b', 'c'],
        ['d', 'e', 'f'],
        ['g', 'h', 'i']);

        // should parse a triple with a literal
        $this->shouldParse('<a> <b> "string".',
        ['a', 'b', '"string"']);

        // should parse a triple with a numeric literal
        $this->shouldParse('<a> <b> 3.0.',
        ['a', 'b', '"3.0"^^http://www.w3.org/2001/XMLSchema#decimal']);

        // should parse a triple with an integer literal
        $this->shouldParse('<a> <b> 3.',
        ['a', 'b', '"3"^^http://www.w3.org/2001/XMLSchema#integer']);

        // should parse a triple with a floating point literal
        $this->shouldParse('<a> <b> 1.3e2.',
        ['a', 'b', '"1.3e2"^^http://www.w3.org/2001/XMLSchema#double']);

        // should parse a triple with a boolean literal
        $this->shouldParse('<a> <b> true.',
        ['a', 'b', '"true"^^http://www.w3.org/2001/XMLSchema#boolean']);

        // should parse a triple with a literal and a language code
        $this->shouldParse('<a> <b> "string"@en.',
        ['a', 'b', '"string"@en']);

        // should normalize language codes to lowercase
        $this->shouldParse('<a> <b> "string"@EN.',
        ['a', 'b', '"string"@en']);

        // should parse a triple with a literal and an IRI type
        $this->shouldParse('<a> <b> "string"^^<type>.',
        ['a', 'b', '"string"^^type']);

        // should parse a triple with a literal and a prefixed name type
        $this->shouldParse('@prefix x: <y#>. <a> <b> "string"^^x:z.',
        ['a', 'b', '"string"^^y#z']);

        // should differentiate between IRI and prefixed name types
        $this->shouldParse('@prefix : <noturn:>. :a :b "x"^^<urn:foo>. :a :b "x"^^:urn:foo.',
        ['noturn:a', 'noturn:b', '"x"^^urn:foo'],
        ['noturn:a', 'noturn:b', '"x"^^noturn:urn:foo']);

        // should not parse a triple with a literal and a prefixed name type with an inexistent prefix
        $this->shouldNotParse('<a> <b> "string"^^x:z.',
          'Undefined prefix "x:" on line 1.');

        // should parse a triple with the "a" shorthand predicate
        $this->shouldParse('<a> a <t>.',
        ['a', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 't']);

        // should parse triples with prefixes
        $this->shouldParse("@prefix : <#>.\n".
        "@prefix a: <a#>.\n".
        ':x a:a a:b.',
        ['#x', 'a#a', 'a#b']);

        // should parse triples with the prefix "prefix"
        $this->shouldParse('@prefix prefix: <http://prefix.cc/>.'.
        'prefix:a prefix:b prefix:c.',
        ['http://prefix.cc/a', 'http://prefix.cc/b', 'http://prefix.cc/c']);

        // should parse triples with the prefix "base"
        $this->shouldParse('PREFIX base: <http://prefix.cc/>'.
        'base:a base:b base:c.',
        ['http://prefix.cc/a', 'http://prefix.cc/b', 'http://prefix.cc/c']);

        // should parse triples with the prefix "graph"
        $this->shouldParse('PREFIX graph: <http://prefix.cc/>'.
        'graph:a graph:b graph:c.',
        ['http://prefix.cc/a', 'http://prefix.cc/b', 'http://prefix.cc/c']);

        // should not parse @PREFIX
        $this->shouldNotParse('@PREFIX : <#>.',
        'Expected entity but got @PREFIX on line 1.');

        // should parse triples with prefixes and different punctuation
        $this->shouldParse("@prefix : <#>.\n".
        "@prefix a: <a#>.\n".
        ':x a:a a:b;a:c a:d,a:e.',
        ['#x', 'a#a', 'a#b'],
        ['#x', 'a#c', 'a#d'],
        ['#x', 'a#c', 'a#e']);

        // should not parse undefined empty prefix in subject
        $this->shouldNotParse(':a ',
        'Undefined prefix ":" on line 1.');

        // should not parse undefined prefix in subject
        $this->shouldNotParse('a:a ',
        'Undefined prefix "a:" on line 1.');

        // should not parse undefined prefix in predicate
        $this->shouldNotParse('<a> b:c <d> .',
        'Undefined prefix "b:" on line 1.');

        // should not parse undefined prefix in object
        $this->shouldNotParse('<a> <b> c:d .',
        'Undefined prefix "c:" on line 1.');

        // should not parse undefined prefix in datatype
        $this->shouldNotParse('<a> <b> "c"^^d:e .',
        'Undefined prefix "d:" on line 1.');

        // should parse triples with SPARQL prefixes
        $this->shouldParse("PREFIX : <#>\n".
        'PrEfIX a: <a#> '.
        ':x a:a a:b.',
        ['#x', 'a#a', 'a#b']);

        // should not parse prefix declarations without prefix
        $this->shouldNotParse('@prefix <a> ',
        'Expected prefix to follow @prefix on line 1.');

        // should not parse prefix declarations without IRI
        $this->shouldNotParse('@prefix : .',
        'Expected IRI to follow prefix ":" on line 1.');

        // should not parse prefix declarations without a dot
        $this->shouldNotParse('@prefix : <a> ;',
        'Expected declaration to end with a dot on line 1.');

        // should parse statements with shared subjects
        $this->shouldParse("<a> <b> <c>;\n<d> <e>.",
        ['a', 'b', 'c'],
        ['a', 'd', 'e']);

        // should parse statements with shared subjects and trailing semicolon
        $this->shouldParse("<a> <b> <c>;\n<d> <e>;\n.",
        ['a', 'b', 'c'],
        ['a', 'd', 'e']);

        // should parse statements with shared subjects and multiple semicolons
        $this->shouldParse("<a> <b> <c>;;\n<d> <e>.",
        ['a', 'b', 'c'],
        ['a', 'd', 'e']);

        // should parse statements with shared subjects and predicates
        $this->shouldParse('<a> <b> <c>, <d>.',
        ['a', 'b', 'c'],
        ['a', 'b', 'd']);
    }

    public function testBlankNodes(): void
    {
        // should parse diamonds
        $this->shouldParse("<> <> <> <>.\n(<>) <> (<>) <>.",
        ['', '', '', ''],
        ['_:b0', '', '_:b1', ''],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', ''],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', ''],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with named blank nodes
        $this->shouldParse('_:a <b> _:c.',
        ['_:b0_a', 'b', '_:b0_c']);

        // should not parse statements with blank predicates
        $this->shouldNotParse('<a> _:b <c>.',
        'Disallowed blank node as predicate on line 1.');

        // should parse statements with empty blank nodes
        $this->shouldParse('[] <b> [].',
        ['_:b0', 'b', '_:b1']);

        // should parse statements with unnamed blank nodes in the subject
        $this->shouldParse('[<a> <b>] <c> <d>.',
        ['_:b0', 'c', 'd'],
        ['_:b0', 'a', 'b']);

        // should parse statements with unnamed blank nodes in the object
        $this->shouldParse('<a> <b> [<c> <d>].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'c', 'd']);

        // should parse statements with unnamed blank nodes with a string object
        $this->shouldParse('<a> <b> [<c> "x"].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'c', '"x"']);

        // should not parse a blank node with missing subject
        $this->shouldNotParse('<a> <b> [<c>].',
        'Expected entity but got ] on line 1.');

        // should not parse a blank node with only a semicolon
        $this->shouldNotParse('<a> <b> [;].',
        'Unexpected ] on line 1.');

        // should parse a blank node with a trailing semicolon
        $this->shouldParse('<a> <b> [ <u> <v>; ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v']);

        // should parse a blank node with multiple trailing semicolons
        $this->shouldParse('<a> <b> [ <u> <v>;;; ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v']);

        // should parse a multi-predicate blank node
        $this->shouldParse('<a> <b> [ <u> <v>; <w> <z> ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', 'z']);

        // should parse a multi-predicate blank node with multiple semicolons
        $this->shouldParse('<a> <b> [ <u> <v>;;; <w> <z> ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', 'z']);

        // should parse a multi-object blank node
        $this->shouldParse('<a> <b> [ <u> <v>, <z> ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'u', 'z']);

        // should parse a multi-statement blank node ending with a literal
        $this->shouldParse('<a> <b> [ <u> <v>; <w> "z" ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', '"z"']);

        // should parse a multi-statement blank node ending with a typed literal
        $this->shouldParse('<a> <b> [ <u> <v>; <w> "z"^^<t> ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', '"z"^^t']);

        // should parse a multi-statement blank node ending with a string with language
        $this->shouldParse('<a> <b> [ <u> <v>; <w> "z"^^<t> ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', '"z"^^t']);

        // should parse a multi-statement blank node with trailing semicolon
        $this->shouldParse('<a> <b> [ <u> <v>; <w> <z>; ].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'u', 'v'],
        ['_:b0', 'w', 'z']);

        // should parse statements with nested blank nodes in the subject
        $this->shouldParse('[<a> [<x> <y>]] <c> <d>.',
        ['_:b0', 'c', 'd'],
        ['_:b0', 'a', '_:b1'],
        ['_:b1', 'x', 'y']);

        // should parse statements with nested blank nodes in the object
        $this->shouldParse('<a> <b> [<c> [<d> <e>]].',
        ['a', 'b', '_:b0'],
        ['_:b0', 'c', '_:b1'],
        ['_:b1', 'd', 'e']);

        // should reuse identifiers of blank nodes within and outside of graphs
        $this->shouldParse('_:a <b> _:c. <g> { _:a <b> _:c }',
        ['_:b0_a', 'b', '_:b0_c'],
        ['_:b0_a', 'b', '_:b0_c', 'g']);

        // should not parse an invalid blank node
        $this->shouldNotParse('[ <a> <b> .',
        'Expected punctuation to follow "b" on line 1.');

        // should parse a statements with only an anonymous node
        $this->shouldParse('[<p> <o>].',
        ['_:b0', 'p', 'o']);

        // should not parse a statement with only a blank anonymous node
        $this->shouldNotParse('[].',
        'Unexpected . on line 1.');

        // should not parse an anonymous node with only an anonymous node inside
        $this->shouldNotParse('[[<p> <o>]].',
        'Expected entity but got [ on line 1.');

        // should parse statements with an empty list in the subject
        $this->shouldParse('() <a> <b>.',
        ['http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'a', 'b']);

        // should parse statements with an empty list in the object
        $this->shouldParse('<a> <b> ().',
        ['a', 'b', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a single-element list in the subject
        $this->shouldParse('(<x>) <a> <b>.',
        ['_:b0', 'a', 'b'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a single-element list in the object
        $this->shouldParse('<a> <b> (<x>).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse a list with a literal
        $this->shouldParse('<a> <b> ("x").',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"x"'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse a list with a typed literal
        $this->shouldParse('<a> <b> ("x"^^<y>).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"x"^^y'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse a list with a language-tagged literal
        $this->shouldParse('<a> <b> ("x"@en-GB).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"x"@en-gb'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a multi-element list in the subject
        $this->shouldParse('(<x> <y>) <a> <b>.',
        ['_:b0', 'a', 'b'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a multi-element list in the object
        $this->shouldParse('<a> <b> (<x> <y>).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a multi-element literal list in the object
        $this->shouldParse('<a> <b> ("x" "y"@en-GB "z"^^<t>).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"x"'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"y"@en-gb'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b2'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '"z"^^t'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with prefixed names in lists
        $this->shouldParse('@prefix a: <a#>. <a> <b> (a:x a:y).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a#x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a#y'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should not parse statements with undefined prefixes in lists
        $this->shouldNotParse('<a> <b> (a:x a:y).',
        'Undefined prefix "a:" on line 1.');

        // should parse statements with blank nodes in lists
        $this->shouldParse('<a> <b> (_:x _:y).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b0_x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b0_y'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a nested empty list
        $this->shouldParse('<a> <b> (<x> ()).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with non-empty nested lists
        $this->shouldParse('<a> <b> (<x> (<y>)).',
        ['a', 'b', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b2'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a list containing a blank node
        $this->shouldParse('([]) <a> <b>.',
        ['_:b0', 'a', 'b'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b1'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should parse statements with a list containing multiple blank nodes
        $this->shouldParse('([] [<x> <y>]) <a> <b>.',
        ['_:b0', 'a', 'b'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b1'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b2'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b3'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b3', 'x', 'y']);

        // should parse statements with a blank node containing a list
        $this->shouldParse('[<a> (<b>)] <c> <d>.',
        ['_:b0', 'c', 'd'],
        ['_:b0', 'a', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'b'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should not parse an invalid list
        $this->shouldNotParse('<a> <b> (]).',
        'Expected entity but got ] on line 1.');

        // should resolve IRIs against @base
        $this->shouldParse("@base <http://ex.org/>.\n".
        "<a> <b> <c>.\n".
        "@base <d/>.\n".
        '<e> <f> <g>.',
        ['http://ex.org/a', 'http://ex.org/b', 'http://ex.org/c'],
        ['http://ex.org/d/e', 'http://ex.org/d/f', 'http://ex.org/d/g']);

        // should not resolve IRIs against @BASE
        $this->shouldNotParse('@BASE <http://ex.org/>.',
        'Expected entity but got @BASE on line 1.');

        // should resolve IRIs against SPARQL base
        $this->shouldParse("BASE <http://ex.org/>\n".
        '<a> <b> <c>. '.
        'BASE <d/> '.
        '<e> <f> <g>.',
        ['http://ex.org/a', 'http://ex.org/b', 'http://ex.org/c'],
        ['http://ex.org/d/e', 'http://ex.org/d/f', 'http://ex.org/d/g']);

        // should resolve IRIs against a @base with query string
        $this->shouldParse("@base <http://ex.org/?foo>.\n".
        "<> <b> <c>.\n".
        "@base <d/?bar>.\n".
        '<> <f> <g>.',
        ['http://ex.org/?foo', 'http://ex.org/b', 'http://ex.org/c'],
        ['http://ex.org/d/?bar', 'http://ex.org/d/f', 'http://ex.org/d/g']);

        // should resolve IRIs with query string against @base
        $this->shouldParse("@base <http://ex.org/>.\n".
        "<?> <?a> <?a=b>.\n".
        "@base <d>.\n".
        '<?> <?a> <?a=b>.'.
        "@base <?e>.\n".
        '<> <?a> <?a=b>.',
        ['http://ex.org/?', 'http://ex.org/?a', 'http://ex.org/?a=b'],
        ['http://ex.org/d?', 'http://ex.org/d?a', 'http://ex.org/d?a=b'],
        ['http://ex.org/d?e', 'http://ex.org/d?a', 'http://ex.org/d?a=b']);

        // should not resolve IRIs with colons
        $this->shouldParse("@base <http://ex.org/>.\n".
        "<a>   <b>   <c>.\n".
        "<A:>  <b:>  <c:>.\n".
        '<a:a> <b:B> <C-D:c>.',
        ['http://ex.org/a', 'http://ex.org/b', 'http://ex.org/c'],
        ['A:',  'b:',  'c:'],
        ['a:a', 'b:B', 'C-D:c']);

        // should resolve datatype IRIs against @base
        $this->shouldParse("@base <http://ex.org/>.\n".
        "<a> <b> \"c\"^^<d>.\n".
        "@base <d/>.\n".
        '<e> <f> "g"^^<h>.',
        ['http://ex.org/a', 'http://ex.org/b', '"c"^^http://ex.org/d'],
        ['http://ex.org/d/e', 'http://ex.org/d/f', '"g"^^http://ex.org/d/h']);

        // should resolve IRIs against a base with a fragment
        $this->shouldParse("@base <http://ex.org/foo#bar>.\n".
        "<a> <b> <#c>.\n",
        ['http://ex.org/a', 'http://ex.org/b', 'http://ex.org/foo#c']);

        // should resolve IRIs with an empty fragment
        $this->shouldParse("@base <http://ex.org/foo>.\n".
        "<#> <b#> <#c>.\n",
        ['http://ex.org/foo#', 'http://ex.org/b#', 'http://ex.org/foo#c']);
        // should not resolve prefixed names
        $this->shouldParse('PREFIX ex: <http://ex.org/a/bb/ccc/../>'."\n".
        'ex:a ex:b ex:c .',
        ['http://ex.org/a/bb/ccc/../a', 'http://ex.org/a/bb/ccc/../b', 'http://ex.org/a/bb/ccc/../c']);

        // should parse an empty default graph
        $this->shouldParse('{}');

        // should parse a one-triple default graph ending without a dot
        $this->shouldParse('{<a> <b> <c>}',
        ['a', 'b', 'c']);

        // should parse a one-triple default graph ending with a dot
        $this->shouldParse('{<a> <b> <c>.}',
        ['a', 'b', 'c']);

        // should parse a three-triple default graph ending without a dot
        $this->shouldParse('{<a> <b> <c>;<d> <e>,<f>}',
        ['a', 'b', 'c'],
        ['a', 'd', 'e'],
        ['a', 'd', 'f']);

        // should parse a three-triple default graph ending with a dot
        $this->shouldParse('{<a> <b> <c>;<d> <e>,<f>.}',
        ['a', 'b', 'c'],
        ['a', 'd', 'e'],
        ['a', 'd', 'f']);

        // should parse a three-triple default graph ending with a semicolon
        $this->shouldParse('{<a> <b> <c>;<d> <e>,<f>;}',
        ['a', 'b', 'c'],
        ['a', 'd', 'e'],
        ['a', 'd', 'f']);

        // should parse an empty named graph with an IRI
        $this->shouldParse('<g>{}');

        // should parse a one-triple named graph with an IRI ending without a dot
        $this->shouldParse('<g> {<a> <b> <c>}',
        ['a', 'b', 'c', 'g']);

        // should parse a one-triple named graph with an IRI ending with a dot
        $this->shouldParse('<g>{<a> <b> <c>.}',
        ['a', 'b', 'c', 'g']);

        // should parse a three-triple named graph with an IRI ending without a dot
        $this->shouldParse('<g> {<a> <b> <c>;<d> <e>,<f>}',
        ['a', 'b', 'c', 'g'],
        ['a', 'd', 'e', 'g'],
        ['a', 'd', 'f', 'g']);

        // should parse a three-triple named graph with an IRI ending with a dot
        $this->shouldParse('<g>{<a> <b> <c>;<d> <e>,<f>.}',
        ['a', 'b', 'c', 'g'],
        ['a', 'd', 'e', 'g'],
        ['a', 'd', 'f', 'g']);

        // should parse an empty named graph with a prefixed name
        $this->shouldParse("@prefix g: <g#>.\ng:h {}");

        // should parse a one-triple named graph with a prefixed name ending without a dot
        $this->shouldParse("@prefix g: <g#>.\ng:h {<a> <b> <c>}",
        ['a', 'b', 'c', 'g#h']);

        // should parse a one-triple named graph with a prefixed name ending with a dot
        $this->shouldParse("@prefix g: <g#>.\ng:h{<a> <b> <c>.}",
        ['a', 'b', 'c', 'g#h']);

        // should parse a three-triple named graph with a prefixed name ending without a dot
        $this->shouldParse('@prefix g: <g#>.'."\n".'g:h {<a> <b> <c>;<d> <e>,<f>}',
        ['a', 'b', 'c', 'g#h'],
        ['a', 'd', 'e', 'g#h'],
        ['a', 'd', 'f', 'g#h']);

        // should parse a three-triple named graph with a prefixed name ending with a dot
        $this->shouldParse('@prefix g: <g#>.'."\n".'g:h{<a> <b> <c>;<d> <e>,<f>.}',
        ['a', 'b', 'c', 'g#h'],
        ['a', 'd', 'e', 'g#h'],
        ['a', 'd', 'f', 'g#h']);

        // should parse an empty anonymous graph
        $this->shouldParse('[] {}');

        // should parse a one-triple anonymous graph ending without a dot
        $this->shouldParse('[] {<a> <b> <c>}',
        ['a', 'b', 'c', '_:b0']);

        // should parse a one-triple anonymous graph ending with a dot
        $this->shouldParse('[]{<a> <b> <c>.}',
        ['a', 'b', 'c', '_:b0']);

        // should parse a three-triple anonymous graph ending without a dot
        $this->shouldParse('[] {<a> <b> <c>;<d> <e>,<f>}',
        ['a', 'b', 'c', '_:b0'],
        ['a', 'd', 'e', '_:b0'],
        ['a', 'd', 'f', '_:b0']);

        // should parse a three-triple anonymous graph ending with a dot
        $this->shouldParse('[]{<a> <b> <c>;<d> <e>,<f>.}',
        ['a', 'b', 'c', '_:b0'],
        ['a', 'd', 'e', '_:b0'],
        ['a', 'd', 'f', '_:b0']);

        // should parse an empty named graph with an IRI and the GRAPH keyword
        $this->shouldParse('GRAPH <g> {}');

        // should parse an empty named graph with a prefixed name and the GRAPH keyword
        $this->shouldParse('@prefix g: <g#>.'."\n".'GRAPH g:h {}');

        // should parse an empty anonymous graph and the GRAPH keyword
        $this->shouldParse('GRAPH [] {}');

        // should parse a one-triple named graph with an IRI and the GRAPH keyword
        $this->shouldParse('GRAPH <g> {<a> <b> <c>}',
        ['a', 'b', 'c', 'g']);

        // should parse a one-triple named graph with a prefixed name and the GRAPH keyword
        $this->shouldParse('@prefix g: <g#>.'."\n".'GRAPH g:h {<a> <b> <c>}',
        ['a', 'b', 'c', 'g#h']);

        // should parse a one-triple anonymous graph and the GRAPH keyword
        $this->shouldParse('GRAPH [] {<a> <b> <c>}',
        ['a', 'b', 'c', '_:b0']);
    }

    public function testLiterals(): void
    {
        // should parse triple quotes
        $this->shouldParse('<a> <b> """ abc """.',
        ['a', 'b', '" abc "']);

        // should parse triple quotes with a newline
        $this->shouldParse("<a> <b> \"\"\" abc\nabc \"\"\".",
        ['a', 'b', '" abc'."\n".'abc "']);
    }

    public function testUnicode(): void
    {
        // should parse a graph with 8-bit unicode escape sequences
        $this->shouldParse('<\\U0001d400> {'."\n".'<\\U0001d400> <\\U0001d400> "\\U0001d400"^^<\\U0001d400>'."\n".'}'."\n",
        ['𝐀', '𝐀', '"𝐀"^^𝐀', '𝐀']);
        $this->shouldParse('@prefix c: <http://example.org/>.
        @prefix c: <http://example.org/>.
c:test <b> <http://example.org/テスト> .', ['http://example.org/test', 'b', 'http://example.org/テスト', '']);

        // should parse unicode after prefix
        $this->shouldParse('@prefix c: <http://example.org/>.
c:test <b> c:テスト .', ['http://example.org/test', 'b', 'http://example.org/テスト', '']);

        // should parse unicode in literal
        $this->shouldParse('@prefix c: <http://example.org/>.
c:test <b> "c:テスト" .', ['http://example.org/test', 'b', '"c:テスト"', '']);

        // should parse unicode in prefixname
        $this->shouldParse('@prefix c: <http://example.org/テ>.
<http://example.org/testprefixname> <b> c:スト .', ['http://example.org/testprefixname', 'b', 'http://example.org/テスト', '']);
    }

    public function testParseErrors(): void
    {
        // should not parse a single closing brace
        $this->shouldNotParse('}',
        'Unexpected graph closing on line 1.');

        // should not parse a single opening brace
        $this->shouldNotParse('{',
        'Expected entity but got eof on line 1.');

        // should not parse a superfluous closing brace
        $this->shouldNotParse('{}}',
        'Unexpected graph closing on line 1.');

        // should not parse a graph with only a dot
        $this->shouldNotParse('{.}',
        'Expected entity but got . on line 1.');

        // should not parse a graph with only a semicolon
        $this->shouldNotParse('{;}',
        'Expected entity but got ; on line 1.');

        // should not parse an unclosed graph
        $this->shouldNotParse('{<a> <b> <c>.',
        'Unclosed graph on line 1.');

        // should not parse a named graph with a list node as label
        $this->shouldNotParse('() {}',
        'Expected entity but got { on line 1.');

        // should not parse a named graph with a non-empty blank node as label
        $this->shouldNotParse('[<a> <b>] {}',
        'Expected entity but got { on line 1.');

        // should not parse a named graph with the GRAPH keyword and a non-empty blank node as label
        $this->shouldNotParse('GRAPH [<a> <b>] {}',
        'Invalid graph label on line 1.');

        // should not parse a triple after the GRAPH keyword
        $this->shouldNotParse('GRAPH <a> <b> <c>.',
        'Expected graph but got IRI on line 1.');

        // should not parse repeated GRAPH keywords
        $this->shouldNotParse('GRAPH GRAPH <g> {}',
        'Invalid graph label on line 1.');

        // should parse a quad with 4 IRIs
        $this->shouldParse('<a> <b> <c> <g>.',
        ['a', 'b', 'c', 'g']);

        // should parse a quad with 4 prefixed names
        $this->shouldParse('@prefix p: <p#>.'."\n".'p:a p:b p:c p:g.',
        ['p#a', 'p#b', 'p#c', 'p#g']);

        // should not parse a quad with an undefined prefix
        $this->shouldNotParse('<a> <b> <c> p:g.',
        'Undefined prefix "p:" on line 1.');

        // should parse a quad with 3 IRIs and a literal
        $this->shouldParse('<a> <b> "c"^^<d> <g>.',
        ['a', 'b', '"c"^^d', 'g']);

        // should parse a quad with 2 blank nodes and a literal
        $this->shouldParse('_:a <b> "c"^^<d> _:g.',
        ['_:b0_a', 'b', '"c"^^d', '_:b0_g']);

        // should not parse a quad in a graph
        $this->shouldNotParse('{<a> <b> <c> <g>.}',
        'Expected punctuation to follow "c" on line 1.');

        // should not parse a quad with different punctuation
        $this->shouldNotParse('<a> <b> <c> <g>;',
        'Expected dot to follow quad on line 1.');

        // should not parse base declarations without IRI
        $this->shouldNotParse('@base a: ',
        'Expected IRI to follow base declaration on line 1.');

        // should not parse improperly nested parentheses and brackets
        $this->shouldNotParse('<a> <b> [<c> (<d>]).',
        'Expected entity but got ] on line 1.');

        // should not parse improperly nested square brackets
        $this->shouldNotParse('<a> <b> [<c> <d>]].',
        'Expected entity but got ] on line 1.');

        // should error when an object is not there
        $this->shouldNotParse('<a> <b>.',
        'Expected entity but got . on line 1.');

        // should error when a dot is not there
        $this->shouldNotParse('<a> <b> <c>',
        'Expected entity but got eof on line 1.');

        // should error with an abbreviation in the subject
        $this->shouldNotParse('a <a> <a>.',
        'Expected entity but got abbreviation on line 1.');

        // should error with an abbreviation in the object
        $this->shouldNotParse('<a> <a> a .',
        'Expected entity but got abbreviation on line 1.');

        // should error if punctuation follows a subject
        $this->shouldNotParse('<a> .',
        'Unexpected . on line 1.');

        // should error if an unexpected token follows a subject
        $this->shouldNotParse('<a> [',
        'Expected entity but got [ on line 1.');
    }

    public function testInterface(): void
    {
        $prefixes = [];
        $tripleCallback = function ($error, $triple) use (&$prefixes) {
            //when end of stream
            if (!isset($triple)) {
                $this->assertEquals(2, \count(array_keys($prefixes)));
            }
        };

        $prefixCallback = function ($prefix, $iri) use (&$prefixes) {
            //$this->assertExists($prefix);
            //$this->assertExists($iri);
            $prefixes[$prefix] = $iri;
        };

        // should return prefixes through a callback function
        (new TriGParser())->parse('@prefix a: <IRIa>. a:a a:b a:c. @prefix b: <IRIb>.', $tripleCallback, $prefixCallback);

        // should return prefixes through a callback without triple callback function (done) {
        $prefixes = [];
        $prefixCallback = function ($prefix, $iri) use (&$prefixes) {
            $prefixes[$prefix] = $iri;
        };
        (new TriGParser())->parse('@prefix a: <IRIa>. a:a a:b a:c. @prefix b: <IRIb>.', null, $prefixCallback);

        $this->assertEquals(2, \count(array_keys($prefixes)));

        // should return prefixes at the last triple callback function (done) {
        $tripleCallback = function ($error, $triple) use (&$prefixes) {
            if (!isset($triple)) {
                $this->assertEquals(2, \count(array_keys($prefixes)));
            }
        };
        (new TriGParser())->parse('@prefix a: <IRIa>. a:a a:b a:c. @prefix b: <IRIb>.', $tripleCallback);

        // should parse a string synchronously if no callback is given function () {
        $triples = (new TriGParser())->parse('@prefix a: <urn:a:>. a:a a:b a:c.');
        $this->assertEquals([['subject' => 'urn:a:a', 'predicate' => 'urn:a:b', 'object' => 'urn:a:c', 'graph' => '']], $triples);
    }

    public function testParsingChunks(): void
    {
        $count = 0;
        $parser = new TriGParser([], function ($error, $triple) use (&$count) {
            if (isset($triple)) {
                $this->assertEquals($triple, ['subject' => 'http://ex.org/a', 'predicate' => 'http://ex.org/b', 'object' => 'http://ex.org/c', 'graph' => '']);
                ++$count;
            } elseif (isset($error)) {
                throw $error;
            }
        });
        $parser->parseChunk('@prefix a: <http://ex.org/>. a:a a:b a:c.'."\n");
        $parser->parseChunk('@prefix a: <http://ex.org/>. a:a a:b a:c.'."\n");
        $parser->parseChunk('@prefix a: <http://ex.org/>. a:a a:b a:c.'."\n");
        $this->assertEquals(3, $count);
    }

    public function testParsingWithLiteralNewline(): void
    {
        // With a newline
        $count = 0;
        $parser = new TriGParser([], function ($error, $triple) use (&$count) {
            if (isset($triple)) {
                $this->assertEquals($triple, ['subject' => 'http://ex.org/a', 'predicate' => 'http://ex.org/b', 'object' => "\"\n\"", 'graph' => '']);
                ++$count;
            } elseif (!isset($triple) && isset($error)) {
                throw $error;
            }
        });
        $parser->parseChunk('@prefix a: <http://ex.org/>. a:a a:b """'."\n");
        $parser->parseChunk('""".');
        $parser->end();
        $this->assertEquals(1, $count);
    }

    public function testException(): void
    {
        // should throw on syntax errors if no callback is given function () {
        try {
            (new TriGParser())->parse('<a> bar <c>');
        } catch (\Exception $e) {
            $this->assertEquals('Unexpected "bar" on line 1.', $e->getMessage());
        }

        // should throw on grammar errors if no callback is given function () {
        try {
            (new TriGParser())->parse('<a> <b> <c>');
        } catch (\Exception $e) {
            $this->assertEquals('Expected punctuation to follow "c" on line 1.', $e->getMessage());
        }
    }

    public function testParserWithIRI(): void
    {
        $parser = function () { return new TriGParser(['documentIRI' => 'http://ex.org/x/yy/zzz/f.ttl']); };

        // should resolve IRIs against the document IRI
        $this->shouldParse($parser,
        '@prefix : <#>.'."\n".
        '<a> <b> <c> <g>.'."\n".
        ':d :e :f :g.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', 'http://ex.org/x/yy/zzz/c', 'http://ex.org/x/yy/zzz/g'],
        ['http://ex.org/x/yy/zzz/f.ttl#d', 'http://ex.org/x/yy/zzz/f.ttl#e', 'http://ex.org/x/yy/zzz/f.ttl#f', 'http://ex.org/x/yy/zzz/f.ttl#g']);

        // should resolve IRIs with a trailing slash against the document IRI
        $this->shouldParse($parser,
        '</a> </a/b> </a/b/c>.'."\n",
        ['http://ex.org/a', 'http://ex.org/a/b', 'http://ex.org/a/b/c']);

        // should resolve IRIs starting with ./ against the document IRI
        $this->shouldParse($parser,
        '<./a> <./a/b> <./a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/a/b', 'http://ex.org/x/yy/zzz/a/b/c']);

        // should resolve IRIs starting with multiple ./ sequences against the document IRI
        $this->shouldParse($parser,
        '<./././a> <./././././a/b> <././././././a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/a/b', 'http://ex.org/x/yy/zzz/a/b/c']);

        // should resolve IRIs starting with ../ against the document IRI
        $this->shouldParse($parser,
        '<../a> <../a/b> <../a/b/c>.'."\n",
        ['http://ex.org/x/yy/a', 'http://ex.org/x/yy/a/b', 'http://ex.org/x/yy/a/b/c']);

        // should resolve IRIs starting multiple ../ sequences against the document IRI
        $this->shouldParse($parser,
        '<../../a> <../../../a/b> <../../../../../../../../a/b/c>.'."\n",
        ['http://ex.org/x/a', 'http://ex.org/a/b', 'http://ex.org/a/b/c']);

        // should resolve IRIs starting with mixes of ./ and ../ sequences against the document IRI
        $this->shouldParse($parser,
        '<.././a> <./.././a/b> <./.././.././a/b/c>.'."\n",
        ['http://ex.org/x/yy/a', 'http://ex.org/x/yy/a/b', 'http://ex.org/x/a/b/c']);

        // should resolve IRIs starting with .x, ..x, or .../ against the document IRI
        $this->shouldParse($parser,
        '<.x/a> <..x/a/b> <.../a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/.x/a', 'http://ex.org/x/yy/zzz/..x/a/b', 'http://ex.org/x/yy/zzz/.../a/b/c']);

        // should resolve datatype IRIs against the document IRI
        $this->shouldParse($parser,
        '<a> <b> "c"^^<d>.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', '"c"^^http://ex.org/x/yy/zzz/d']);

        // should resolve IRIs in lists against the document IRI
        $this->shouldParse($parser,
        '(<a> <b>) <p> (<c> <d>).',
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/a'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/b'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b0', 'http://ex.org/x/yy/zzz/p', '_:b2'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/c'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/d'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should respect @base statements
        $this->shouldParse($parser,
        '<a> <b> <c>.'."\n".
        '@base <http://ex.org/x/>.'."\n".
        '<e> <f> <g>.'."\n".
        '@base <d/>.'."\n".
        '<h> <i> <j>.'."\n".
        '@base </e/>.'."\n".
        '<k> <l> <m>.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', 'http://ex.org/x/yy/zzz/c'],
        ['http://ex.org/x/e', 'http://ex.org/x/f', 'http://ex.org/x/g'],
        ['http://ex.org/x/d/h', 'http://ex.org/x/d/i', 'http://ex.org/x/d/j'],
        ['http://ex.org/e/k', 'http://ex.org/e/l', 'http://ex.org/e/m']);
    }

    public function testParserWithDocumentIRI(): void
    {
        $parser = function () {
            return new TriGParser(['documentIRI' => 'http://ex.org/x/yy/zzz/f.ttl']);
        };

        // should resolve IRIs against the document IRI
        $this->shouldParse($parser,
        '@prefix : <#>.'."\n".
        '<a> <b> <c> <g>.'."\n".
        ':d :e :f :g.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', 'http://ex.org/x/yy/zzz/c', 'http://ex.org/x/yy/zzz/g'],
        ['http://ex.org/x/yy/zzz/f.ttl#d', 'http://ex.org/x/yy/zzz/f.ttl#e', 'http://ex.org/x/yy/zzz/f.ttl#f', 'http://ex.org/x/yy/zzz/f.ttl#g']);

        // should resolve IRIs with a trailing slash against the document IRI
        $this->shouldParse($parser,
        '</a> </a/b> </a/b/c>.'."\n",
        ['http://ex.org/a', 'http://ex.org/a/b', 'http://ex.org/a/b/c']);

        // should resolve IRIs starting with ./ against the document IRI
        $this->shouldParse($parser,
        '<./a> <./a/b> <./a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/a/b', 'http://ex.org/x/yy/zzz/a/b/c']);

        // should resolve IRIs starting with multiple ./ sequences against the document IRI
        $this->shouldParse($parser,
        '<./././a> <./././././a/b> <././././././a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/a/b', 'http://ex.org/x/yy/zzz/a/b/c']);

        // should resolve IRIs starting with ../ against the document IRI
        $this->shouldParse($parser,
        '<../a> <../a/b> <../a/b/c>.'."\n",
        ['http://ex.org/x/yy/a', 'http://ex.org/x/yy/a/b', 'http://ex.org/x/yy/a/b/c']);

        // should resolve IRIs starting multiple ../ sequences against the document IRI
        $this->shouldParse($parser,
        '<../../a> <../../../a/b> <../../../../../../../../a/b/c>.'."\n",
        ['http://ex.org/x/a', 'http://ex.org/a/b', 'http://ex.org/a/b/c']);

        // should resolve IRIs starting with mixes of ./ and ../ sequences against the document IRI
        $this->shouldParse($parser,
        '<.././a> <./.././a/b> <./.././.././a/b/c>.'."\n",
        ['http://ex.org/x/yy/a', 'http://ex.org/x/yy/a/b', 'http://ex.org/x/a/b/c']);

        // should resolve IRIs starting with .x, ..x, or .../ against the document IRI
        $this->shouldParse($parser,
        '<.x/a> <..x/a/b> <.../a/b/c>.'."\n",
        ['http://ex.org/x/yy/zzz/.x/a', 'http://ex.org/x/yy/zzz/..x/a/b', 'http://ex.org/x/yy/zzz/.../a/b/c']);

        // should resolve datatype IRIs against the document IRI
        $this->shouldParse($parser,
        '<a> <b> "c"^^<d>.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', '"c"^^http://ex.org/x/yy/zzz/d']);

        // should resolve IRIs in lists against the document IRI
        $this->shouldParse($parser,
        '(<a> <b>) <p> (<c> <d>).',
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/a'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/b'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b0', 'http://ex.org/x/yy/zzz/p', '_:b2'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/c'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'http://ex.org/x/yy/zzz/d'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil']);

        // should respect @base statements
        $this->shouldParse($parser,
        '<a> <b> <c>.'."\n".
        '@base <http://ex.org/x/>.'."\n".
        '<e> <f> <g>.'."\n".
        '@base <d/>.'."\n".
        '<h> <i> <j>.'."\n".
        '@base </e/>.'."\n".
        '<k> <l> <m>.',
        ['http://ex.org/x/yy/zzz/a', 'http://ex.org/x/yy/zzz/b', 'http://ex.org/x/yy/zzz/c'],
        ['http://ex.org/x/e', 'http://ex.org/x/f', 'http://ex.org/x/g'],
        ['http://ex.org/x/d/h', 'http://ex.org/x/d/i', 'http://ex.org/x/d/j'],
        ['http://ex.org/e/k', 'http://ex.org/e/l', 'http://ex.org/e/m']);
    }

    public function testDifferentSettings(): void
    {
        $parser = function () { return new TriGParser(['blankNodePrefix' => '_:blank']); };

        // should use the given prefix for blank nodes
        $this->shouldParse($parser,
        '_:a <b> _:c.'."\n",
        ['_:blanka', 'b', '_:blankc']);

        $parser = function () { return new TriGParser(['blankNodePrefix' => '']); };

        // should not use a prefix for blank nodes
        $this->shouldParse($parser,
        '_:a <b> _:c.'."\n",
        ['_:a', 'b', '_:c']);

        $parser = function () { return new TriGParser(['format' => 1]); };

        // should parse a single triple
        $this->shouldParse($parser, '<a> <b> <c>.', ['a', 'b', 'c']);

        // should parse a graph
        $this->shouldParse($parser, '{<a> <b> <c>}', ['a', 'b', 'c']);
    }

    public function testTurtle(): void
    {
        $parser = function () { return new TriGParser(['format' => 'Turtle']); };

        // should parse a single triple
        $this->shouldParse($parser, '<a> <b> <c>.', ['a', 'b', 'c']);

        // should not parse a default graph
        $this->shouldNotParse($parser, '{}', 'Unexpected graph on line 1.');

        // should not parse a named graph
        $this->shouldNotParse($parser, '<g> {}', 'Expected entity but got { on line 1.');

        // should not parse a named graph with the GRAPH keyword
        $this->shouldNotParse($parser, 'GRAPH <g> {}', 'Expected entity but got GRAPH on line 1.');

        // should not parse a quad
        $this->shouldNotParse($parser, '<a> <b> <c> <d>.', 'Expected punctuation to follow "c" on line 1.');

        // should not parse a variable
        $this->shouldNotParse($parser, '?a ?b ?c.', 'Unexpected "?a" on line 1.');

        // should not parse an equality statement
        $this->shouldNotParse($parser, '<a> = <b>.', 'Unexpected "=" on line 1.');

        // should not parse a right implication statement
        $this->shouldNotParse($parser, '<a> => <b>.', 'Unexpected "=>" on line 1.');

        // should not parse a left implication statement
        $this->shouldNotParse($parser, '<a> <= <b>.', 'Unexpected "<=" on line 1.');

        // should not parse a formula as object
        $this->shouldNotParse($parser, '<a> <b> {}.', 'Unexpected graph on line 1.');
    }

    public function testTriGFormat(): void
    {
        $parser = function () { return new TriGParser(['format' => 'TriG']); };

        // should parse a single triple
        $this->shouldParse($parser, '<a> <b> <c>.', ['a', 'b', 'c']);

        // should parse a default graph
        $this->shouldParse($parser, '{}');

        // should parse a named graph
        $this->shouldParse($parser, '<g> {}');

        // should parse a named graph with the GRAPH keyword
        $this->shouldParse($parser, 'GRAPH <g> {}');

        // should not parse a quad
        $this->shouldNotParse($parser, '<a> <b> <c> <d>.', 'Expected punctuation to follow "c" on line 1.');

        // should not parse a variable
        $this->shouldNotParse($parser, '?a ?b ?c.', 'Unexpected "?a" on line 1.');

        // should not parse an equality statement
        $this->shouldNotParse($parser, '<a> = <b>.', 'Unexpected "=" on line 1.');

        // should not parse a right implication statement
        $this->shouldNotParse($parser, '<a> => <b>.', 'Unexpected "=>" on line 1.');

        // should not parse a left implication statement
        $this->shouldNotParse($parser, '<a> <= <b>.', 'Unexpected "<=" on line 1.');

        // should not parse a formula as object
        $this->shouldNotParse($parser, '<a> <b> {}.', 'Unexpected graph on line 1.');
    }

    public function testNTriplesFormat(): void
    {
        $parser = function () { return new TriGParser(['format' => 'N-Triples']); };

        // should parse a single triple
        $this->shouldParse($parser, '<http://ex.org/a> <http://ex.org/b> "c".',
        ['http://ex.org/a', 'http://ex.org/b', '"c"']);

        // should not parse a single quad
        $this->shouldNotParse($parser, '<http://ex.org/a> <http://ex.org/b> "c" <http://ex.org/g>.',
        'Expected punctuation to follow ""c"" on line 1.');

        // should not parse relative IRIs
        $this->shouldNotParse($parser, '<a> <b> <c>.', 'Disallowed relative IRI on line 1.');

        // should not parse a prefix declaration
        $this->shouldNotParse($parser, '@prefix : <p#>.', 'Unexpected "@prefix" on line 1.');

        // should not parse a variable
        $this->shouldNotParse($parser, '?a ?b ?c.', 'Unexpected "?a" on line 1.');

        // should not parse an equality statement
        $this->shouldNotParse($parser, '<urn:a:a> = <urn:b:b>.', 'Unexpected "=" on line 1.');

        // should not parse a right implication statement
        $this->shouldNotParse($parser, '<urn:a:a> => <urn:b:b>.', 'Unexpected "=>" on line 1.');

        // should not parse a left implication statement
        $this->shouldNotParse($parser, '<urn:a:a> <= <urn:b:b>.', 'Unexpected "<=" on line 1.');

        // should not parse a formula as object
        $this->shouldNotParse($parser, '<urn:a:a> <urn:b:b> {}.', 'Unexpected "{" on line 1.');
    }

    public function testNQuadsFormat(): void
    {
        $parser = function () { return new TriGParser(['format' => 'N-Quads']); };

        // should parse a single triple
        $this->shouldParse($parser, '<http://ex.org/a> <http://ex.org/b> <http://ex.org/c>.',
        ['http://ex.org/a', 'http://ex.org/b', 'http://ex.org/c']);

        // should parse a single quad
        $this->shouldParse($parser, '<http://ex.org/a> <http://ex.org/b> "c" <http://ex.org/g>.',
        ['http://ex.org/a', 'http://ex.org/b', '"c"', 'http://ex.org/g']);

        // should not parse relative IRIs
        $this->shouldNotParse($parser, '<a> <b> <c>.', 'Disallowed relative IRI on line 1.');

        // should not parse a prefix declaration
        $this->shouldNotParse($parser, '@prefix : <p#>.', 'Unexpected "@prefix" on line 1.');

        // should not parse a variable
        $this->shouldNotParse($parser, '?a ?b ?c.', 'Unexpected "?a" on line 1.');

        // should not parse an equality statement
        $this->shouldNotParse($parser, '<urn:a:a> = <urn:b:b>.', 'Unexpected "=" on line 1.');

        // should not parse a right implication statement
        $this->shouldNotParse($parser, '<urn:a:a> => <urn:b:b>.', 'Unexpected "=>" on line 1.');

        // should not parse a left implication statement
        $this->shouldNotParse($parser, '<urn:a:a> <= <urn:b:b>.', 'Unexpected "<=" on line 1.');

        // should not parse a formula as object
        $this->shouldNotParse($parser, '<urn:a:a> <urn:b:b> {}.', 'Unexpected "{" on line 1.');
    }

    public function testN3Format(): void
    {
        $parser = function () { return new TriGParser(['format' => 'N3']); };

        // should parse a single triple
        $this->shouldParse($parser, '<a> <b> <c>.', ['a', 'b', 'c']);

        // should not parse a default graph
        $this->shouldNotParse($parser, '{}', 'Expected entity but got eof on line 1.');

        // should not parse a named graph
        $this->shouldNotParse($parser, '<g> {}', 'Expected entity but got { on line 1.');

        // should not parse a named graph with the GRAPH keyword
        $this->shouldNotParse($parser, 'GRAPH <g> {}', 'Expected entity but got GRAPH on line 1.');

        // should not parse a quad
        $this->shouldNotParse($parser, '<a> <b> <c> <d>.', 'Expected punctuation to follow "c" on line 1.');

        // allows a blank node label in predicate position
        $this->shouldParse($parser, '<a> _:b <c>.', ['a', '_:b0_b', 'c']);

        // should parse a variable
        $this->shouldParse($parser, '?a ?b ?c.', ['?a', '?b', '?c']);

        // should parse a simple equality
        $this->shouldParse($parser, '<a> = <b>.',
        ['a', 'http://www.w3.org/2002/07/owl#sameAs', 'b']);

        // should parse a simple right implication
        $this->shouldParse($parser, '<a> => <b>.',
        ['a', 'http://www.w3.org/2000/10/swap/log#implies', 'b']);

        // should parse a simple left implication
        $this->shouldParse($parser, '<a> <= <b>.',
        ['b', 'http://www.w3.org/2000/10/swap/log#implies', 'a']);

        // should parse a right implication between one-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. } => { <d> <e> ?a }.',
        ['_:b0', 'http://www.w3.org/2000/10/swap/log#implies', '_:b1'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  '?a', '_:b1']);

        // should parse a right implication between two-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. <d> <e> <f>. } => { <d> <e> ?a, <f> }.',
        ['_:b0', 'http://www.w3.org/2000/10/swap/log#implies', '_:b1'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  'f',  '_:b0'],
        ['d',  'e',  '?a', '_:b1'],
        ['d',  'e',  'f',  '_:b1']);

        // should parse a left implication between one-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. } <= { <d> <e> ?a }.',
        ['_:b1', 'http://www.w3.org/2000/10/swap/log#implies', '_:b0'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  '?a', '_:b1']);

        // should parse a left implication between two-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. <d> <e> <f>. } <= { <d> <e> ?a, <f> }.',
        ['_:b1', 'http://www.w3.org/2000/10/swap/log#implies', '_:b0'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  'f',  '_:b0'],
        ['d',  'e',  '?a', '_:b1'],
        ['d',  'e',  'f',  '_:b1']);

        // should parse an equality of one-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. } = { <d> <e> ?a }.',
        ['_:b0', 'http://www.w3.org/2002/07/owl#sameAs', '_:b1'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  '?a', '_:b1']);

        // should parse an equality of two-triple graphs
        $this->shouldParse($parser, '{ ?a ?b <c>. <d> <e> <f>. } = { <d> <e> ?a, <f> }.',
        ['_:b0', 'http://www.w3.org/2002/07/owl#sameAs', '_:b1'],
        ['?a', '?b', 'c',  '_:b0'],
        ['d',  'e',  'f',  '_:b0'],
        ['d',  'e',  '?a', '_:b1'],
        ['d',  'e',  'f',  '_:b1']);

        // should parse nested implication graphs
        $this->shouldParse($parser, '{ { ?a ?b ?c }<={ ?d ?e ?f }. } <= { { ?g ?h ?i } => { ?j ?k ?l } }.',
        ['_:b3', 'http://www.w3.org/2000/10/swap/log#implies', '_:b0'],
        ['_:b2', 'http://www.w3.org/2000/10/swap/log#implies', '_:b1', '_:b0'],
        ['?a', '?b', '?c', '_:b1'],
        ['?d', '?e', '?f', '_:b2'],
        ['_:b4', 'http://www.w3.org/2000/10/swap/log#implies', '_:b5', '_:b3'],
        ['?g', '?h', '?i', '_:b4'],
        ['?j', '?k', '?l', '_:b5']);

        // should not reuse identifiers of blank nodes within and outside of formulas
        $this->shouldParse($parser, '_:a _:b _:c. { _:a _:b _:c } => { { _:a _:b _:c } => { _:a _:b _:c } }.',
        ['_:b0_a', '_:b0_b', '_:b0_c'],
        ['_:b0', 'http://www.w3.org/2000/10/swap/log#implies', '_:b1', ''],
        ['_:b0.a', '_:b0.b', '_:b0.c', '_:b0'],
        ['_:b2', 'http://www.w3.org/2000/10/swap/log#implies', '_:b3', '_:b1'],
        ['_:b2.a', '_:b2.b', '_:b2.c', '_:b2'],
        ['_:b3.a', '_:b3.b', '_:b3.c', '_:b3']);

        // should parse a @forSome statement
        $this->shouldParse($parser, '@forSome <x>. <x> <x> <x>.',
        ['_:b0', '_:b0', '_:b0']);

        // should parse a @forSome statement with multiple entities
        $this->shouldParse($parser, '@prefix a: <a:>. @base <b:>. @forSome a:x, <y>, a:z. a:x <y> a:z.',
        ['_:b0', '_:b1', '_:b2']);

        // should not parse a @forSome statement with an invalid prefix
        $this->shouldNotParse($parser, '@forSome a:b.',
        'Undefined prefix "a:" on line 1.');

        // should not parse a @forSome statement with a blank node
        $this->shouldNotParse($parser, '@forSome _:a.',
        'Unexpected blank on line 1.');

        // should not parse a @forSome statement with a variable
        $this->shouldNotParse($parser, '@forSome ?a.',
        'Unexpected var on line 1.');

        // should correctly scope @forSome statements
        $this->shouldParse($parser, '@forSome <x>. <x> <x> { @forSome <x>. <x> <x> <x>. }. <x> <x> <x>.',
        ['_:b0', '_:b0', '_:b1'],
        ['_:b2', '_:b2', '_:b2', '_:b1'],
        ['_:b0', '_:b0', '_:b0']);

        // should parse a @forAll statement
        $this->shouldParse($parser, '@forAll  <x>. <x> <x> <x>.',
        ['?b-0', '?b-0', '?b-0']);

        // should parse a @forAll statement with multiple entities
        $this->shouldParse($parser, '@prefix a: <a:>. @base <b:>. @forAll  a:x, <y>, a:z. a:x <y> a:z.',
        ['?b-0', '?b-1', '?b-2']);

        // should not parse a @forAll statement with an invalid prefix
        $this->shouldNotParse($parser, '@forAll a:b.',
        'Undefined prefix "a:" on line 1.');

        // should not parse a @forAll statement with a blank node
        $this->shouldNotParse($parser, '@forAll _:a.',
        'Unexpected blank on line 1.');

        // should not parse a @forAll statement with a variable
        $this->shouldNotParse($parser, '@forAll ?a.',
        'Unexpected var on line 1.');

        // should correctly scope @forAll statements
        $this->shouldParse($parser, '@forAll <x>. <x> <x> { @forAll <x>. <x> <x> <x>. }. <x> <x> <x>.',
        ['?b-0', '?b-0', '_:b1'],
        ['?b-2', '?b-2', '?b-2', '_:b1'],
        ['?b-0', '?b-0', '?b-0']);

        // should parse a ! path of length 2 as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        ':joe!fam:mother a fam:Person.',
        ['ex:joe', 'f:mother', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse a ! path of length 4 as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>. @prefix loc: <l:>.'.
        ':joe!fam:mother!loc:office!loc:zip loc:code 1234.',
        ['ex:joe', 'f:mother', '_:b0'],
        ['_:b0',   'l:office', '_:b1'],
        ['_:b1',   'l:zip',    '_:b2'],
        ['_:b2',   'l:code',   '"1234"^^http://www.w3.org/2001/XMLSchema#integer']);

        // should parse a ! path of length 2 as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> :joe!fam:mother.',
        ['x', 'is', '_:b0'],
        ['ex:joe', 'f:mother', '_:b0']);

        // should parse a ! path of length 4 as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>. @prefix loc: <l:>.'.
        '<x> <is> :joe!fam:mother!loc:office!loc:zip.',
        ['x',      'is',       '_:b2'],
        ['ex:joe', 'f:mother', '_:b0'],
        ['_:b0',   'l:office', '_:b1'],
        ['_:b1',   'l:zip',    '_:b2']);

        // should parse a ^ path of length 2 as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        ':joe^fam:son a fam:Person.',
        ['_:b0', 'f:son', 'ex:joe'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse a ^ path of length 4 as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        ':joe^fam:son^fam:sister^fam:mother a fam:Person.',
        ['_:b0', 'f:son',    'ex:joe'],
        ['_:b1', 'f:sister', '_:b0'],
        ['_:b2', 'f:mother', '_:b1'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse a ^ path of length 2 as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> :joe^fam:son.',
        ['x',    'is',    '_:b0'],
        ['_:b0', 'f:son', 'ex:joe']);

        // should parse a ^ path of length 4 as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> :joe^fam:son^fam:sister^fam:mother.',
        ['x',    'is',       '_:b2'],
        ['_:b0', 'f:son',    'ex:joe'],
        ['_:b1', 'f:sister', '_:b0'],
        ['_:b2', 'f:mother', '_:b1']);

        // should parse mixed !/^ paths as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        ':joe!fam:mother^fam:mother a fam:Person.',
        ['ex:joe', 'f:mother', '_:b0'],
        ['_:b1',   'f:mother', '_:b0'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse mixed !/^ paths as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> :joe!fam:mother^fam:mother.',
        ['x', 'is', '_:b1'],
        ['ex:joe', 'f:mother', '_:b0'],
        ['_:b1',   'f:mother', '_:b0']);

        // should parse a ! path in a blank node as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '[fam:knows :joe!fam:mother] a fam:Person.',
        ['_:b0', 'f:knows', '_:b1'],
        ['ex:joe', 'f:mother', '_:b1'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse a ! path in a blank node as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> [fam:knows :joe!fam:mother].',
        ['x', 'is', '_:b0'],
        ['_:b0', 'f:knows', '_:b1'],
        ['ex:joe', 'f:mother', '_:b1']);

        // should parse a ^ path in a blank node as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '[fam:knows :joe^fam:son] a fam:Person.',
        ['_:b0', 'f:knows', '_:b1'],
        ['_:b1', 'f:son', 'ex:joe'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'f:Person']);

        // should parse a ^ path in a blank node as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<x> <is> [fam:knows :joe^fam:son].',
        ['x', 'is', '_:b0'],
        ['_:b0', 'f:knows', '_:b1'],
        ['_:b1', 'f:son', 'ex:joe']);

        // should parse a ! path in a list as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '(<x> :joe!fam:mother <y>) a :List.',
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',  'ex:List'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b2'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['ex:joe', 'f:mother', '_:b2']);

        // should parse a ! path in a list as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<l> <is> (<x> :joe!fam:mother <y>).',
        ['l', 'is', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b2'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['ex:joe', 'f:mother', '_:b2']);

        // should parse a ^ path in a list as subject
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '(<x> :joe^fam:son <y>) a :List.',
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',  'ex:List'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b2'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b2', 'f:son', 'ex:joe']);

        // should parse a ^ path in a list as object
        $this->shouldParse($parser, '@prefix : <ex:>. @prefix fam: <f:>.'.
        '<l> <is> (<x> :joe^fam:son <y>).',
        ['l', 'is', '_:b0'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b1'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', '_:b2'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest',  '_:b3'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'y'],
        ['_:b3', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'],
        ['_:b2', 'f:son', 'ex:joe']);

        // should not parse an invalid ! path
        $this->shouldNotParse($parser, '<a>!"invalid" ', 'Expected entity but got literal on line 1.');

        // should not parse an invalid ^ path
        $this->shouldNotParse($parser, '<a>^"invalid" ', 'Expected entity but got literal on line 1.');
    }

    public function testN3ExplicitQuantifiers(): void
    {
        $parser = function () { return new TriGParser(['format' => 'N3', 'explicitQuantifiers' => true]); };

        // should parse a @forSome statement
        $this->shouldParse($parser, '@forSome <x>. <x> <x> <x>.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forSome', '_:b0', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', 'x']);

        // should parse a @forSome statement with multiple entities
        $this->shouldParse($parser, '@prefix a: <a:>. @base <b:>. @forSome a:x, <y>, a:z. a:x <y> a:z.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forSome', '_:b0',        'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a:x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1', 'urn:n3:quantifiers'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'b:y', 'urn:n3:quantifiers'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b2', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a:z', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['a:x', 'b:y', 'a:z']);

        // should correctly scope @forSome statements
        $this->shouldParse($parser, '@forSome <x>. <x> <x> { @forSome <x>. <x> <x> <x>. }. <x> <x> <x>.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forSome', '_:b0',      'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', '_:b1'],
        ['_:b1', 'http://www.w3.org/2000/10/swap/reify#forSome', '_:b2',  'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', 'x', '_:b1'],
        ['x', 'x', 'x']);

        // should parse a @forAll statement
        $this->shouldParse($parser, '@forAll <x>. <x> <x> <x>.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forAll', '_:b0',       'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', 'x']);

        // should parse a @forAll statement with multiple entities
        $this->shouldParse($parser, '@prefix a: <a:>. @base <b:>. @forAll a:x, <y>, a:z. a:x <y> a:z.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forAll', '_:b0',         'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a:x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b1', 'urn:n3:quantifiers'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'b:y', 'urn:n3:quantifiers'],
        ['_:b1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', '_:b2', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'a:z', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['a:x', 'b:y', 'a:z']);

        // should correctly scope @forAll statements
        $this->shouldParse($parser, '@forAll <x>. <x> <x> { @forAll <x>. <x> <x> <x>. }. <x> <x> <x>.',
        ['', 'http://www.w3.org/2000/10/swap/reify#forAll', '_:b0',       'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b0', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', '_:b1'],
        ['_:b1', 'http://www.w3.org/2000/10/swap/reify#forAll', '_:b2',   'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'x', 'urn:n3:quantifiers'],
        ['_:b2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'urn:n3:quantifiers'],
        ['x', 'x', 'x', '_:b1'],
        ['x', 'x', 'x']);
    }

    public function testResolve(): void
    {
        // IRI resolution
        // RFC3986 normal examples
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', './g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g/', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '?y', 'http://a/bb/ccc/d;p?y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g?y', 'http://a/bb/ccc/g?y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '#s', 'http://a/bb/ccc/d;p?q#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g#s', 'http://a/bb/ccc/g#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g?y#s', 'http://a/bb/ccc/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', ';x', 'http://a/bb/ccc/;x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g;x', 'http://a/bb/ccc/g;x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g;x?y#s', 'http://a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '', 'http://a/bb/ccc/d;p?q');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '.', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', './', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../../g', 'http://a/g');

        // RFC3986 abnormal examples
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g.', 'http://a/bb/ccc/g.');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '.g', 'http://a/bb/ccc/.g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g..', 'http://a/bb/ccc/g..');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', '..g', 'http://a/bb/ccc/..g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', './../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', './g/.', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g/./h', 'http://a/bb/ccc/g/h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g/../h', 'http://a/bb/ccc/h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g;x=1/./y', 'http://a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g;x=1/../y', 'http://a/bb/ccc/y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g?y/./x', 'http://a/bb/ccc/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g?y/../x', 'http://a/bb/ccc/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g#s/./x', 'http://a/bb/ccc/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'g#s/../x', 'http://a/bb/ccc/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q', 'http:g', 'http:g');

        // RFC3986 normal examples with trailing slash in base IRI
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g', 'http://a/bb/ccc/d/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', './g', 'http://a/bb/ccc/d/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g/', 'http://a/bb/ccc/d/g/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '?y', 'http://a/bb/ccc/d/?y');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g?y', 'http://a/bb/ccc/d/g?y');
        $this->itShouldResolve('http://a/bb/ccc/d/', '#s', 'http://a/bb/ccc/d/#s');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g#s', 'http://a/bb/ccc/d/g#s');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g?y#s', 'http://a/bb/ccc/d/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d/', ';x', 'http://a/bb/ccc/d/;x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g;x', 'http://a/bb/ccc/d/g;x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g;x?y#s', 'http://a/bb/ccc/d/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d/', '', 'http://a/bb/ccc/d/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '.', 'http://a/bb/ccc/d/');
        $this->itShouldResolve('http://a/bb/ccc/d/', './', 'http://a/bb/ccc/d/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '..', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../../g', 'http://a/bb/g');

        // RFC3986 abnormal examples with trailing slash in base IRI
        $this->itShouldResolve('http://a/bb/ccc/d/', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g.', 'http://a/bb/ccc/d/g.');
        $this->itShouldResolve('http://a/bb/ccc/d/', '.g', 'http://a/bb/ccc/d/.g');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g..', 'http://a/bb/ccc/d/g..');
        $this->itShouldResolve('http://a/bb/ccc/d/', '..g', 'http://a/bb/ccc/d/..g');
        $this->itShouldResolve('http://a/bb/ccc/d/', './../g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d/', './g/.', 'http://a/bb/ccc/d/g/');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g/./h', 'http://a/bb/ccc/d/g/h');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g/../h', 'http://a/bb/ccc/d/h');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g;x=1/./y', 'http://a/bb/ccc/d/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g;x=1/../y', 'http://a/bb/ccc/d/y');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g?y/./x', 'http://a/bb/ccc/d/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g?y/../x', 'http://a/bb/ccc/d/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g#s/./x', 'http://a/bb/ccc/d/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'g#s/../x', 'http://a/bb/ccc/d/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/d/', 'http:g', 'http:g');

        // RFC3986 normal examples with /. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', './g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g/', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '?y', 'http://a/bb/ccc/./d;p?y');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g?y', 'http://a/bb/ccc/g?y');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '#s', 'http://a/bb/ccc/./d;p?q#s');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g#s', 'http://a/bb/ccc/g#s');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g?y#s', 'http://a/bb/ccc/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', ';x', 'http://a/bb/ccc/;x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g;x', 'http://a/bb/ccc/g;x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g;x?y#s', 'http://a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '', 'http://a/bb/ccc/./d;p?q');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '.', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', './', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../../g', 'http://a/g');

        // RFC3986 abnormal examples with /. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g.', 'http://a/bb/ccc/g.');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '.g', 'http://a/bb/ccc/.g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g..', 'http://a/bb/ccc/g..');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', '..g', 'http://a/bb/ccc/..g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', './../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', './g/.', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g/./h', 'http://a/bb/ccc/g/h');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g/../h', 'http://a/bb/ccc/h');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g;x=1/./y', 'http://a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g;x=1/../y', 'http://a/bb/ccc/y');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g?y/./x', 'http://a/bb/ccc/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g?y/../x', 'http://a/bb/ccc/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g#s/./x', 'http://a/bb/ccc/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'g#s/../x', 'http://a/bb/ccc/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/./d;p?q', 'http:g', 'http:g');

        // RFC3986 normal examples with /.. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', './g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g/', 'http://a/bb/g/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '?y', 'http://a/bb/ccc/../d;p?y');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g?y', 'http://a/bb/g?y');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '#s', 'http://a/bb/ccc/../d;p?q#s');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g#s', 'http://a/bb/g#s');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g?y#s', 'http://a/bb/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', ';x', 'http://a/bb/;x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g;x', 'http://a/bb/g;x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g;x?y#s', 'http://a/bb/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '', 'http://a/bb/ccc/../d;p?q');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '.', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', './', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../../g', 'http://a/g');

        // RFC3986 abnormal examples with /.. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g.', 'http://a/bb/g.');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '.g', 'http://a/bb/.g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g..', 'http://a/bb/g..');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', '..g', 'http://a/bb/..g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', './../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', './g/.', 'http://a/bb/g/');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g/./h', 'http://a/bb/g/h');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g/../h', 'http://a/bb/h');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g;x=1/./y', 'http://a/bb/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g;x=1/../y', 'http://a/bb/y');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g?y/./x', 'http://a/bb/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g?y/../x', 'http://a/bb/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g#s/./x', 'http://a/bb/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'g#s/../x', 'http://a/bb/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/../d;p?q', 'http:g', 'http:g');

        // RFC3986 normal examples with trailing /. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/.', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/.', './g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g/', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/.', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/.', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/.', '?y', 'http://a/bb/ccc/.?y');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g?y', 'http://a/bb/ccc/g?y');
        $this->itShouldResolve('http://a/bb/ccc/.', '#s', 'http://a/bb/ccc/.#s');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g#s', 'http://a/bb/ccc/g#s');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g?y#s', 'http://a/bb/ccc/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/.', ';x', 'http://a/bb/ccc/;x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g;x', 'http://a/bb/ccc/g;x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g;x?y#s', 'http://a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/.', '', 'http://a/bb/ccc/.');
        $this->itShouldResolve('http://a/bb/ccc/.', '.', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/.', './', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/.', '..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/.', '../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/.', '../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/.', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/.', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/.', '../../g', 'http://a/g');

        // RFC3986 abnormal examples with trailing /. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/.', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/.', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/.', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/.', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g.', 'http://a/bb/ccc/g.');
        $this->itShouldResolve('http://a/bb/ccc/.', '.g', 'http://a/bb/ccc/.g');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g..', 'http://a/bb/ccc/g..');
        $this->itShouldResolve('http://a/bb/ccc/.', '..g', 'http://a/bb/ccc/..g');
        $this->itShouldResolve('http://a/bb/ccc/.', './../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/.', './g/.', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g/./h', 'http://a/bb/ccc/g/h');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g/../h', 'http://a/bb/ccc/h');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g;x=1/./y', 'http://a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g;x=1/../y', 'http://a/bb/ccc/y');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g?y/./x', 'http://a/bb/ccc/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g?y/../x', 'http://a/bb/ccc/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g#s/./x', 'http://a/bb/ccc/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'g#s/../x', 'http://a/bb/ccc/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/.', 'http:g', 'http:g');

        // RFC3986 normal examples with trailing /.. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/..', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/..', './g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g/', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/..', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/..', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/..', '?y', 'http://a/bb/ccc/..?y');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g?y', 'http://a/bb/ccc/g?y');
        $this->itShouldResolve('http://a/bb/ccc/..', '#s', 'http://a/bb/ccc/..#s');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g#s', 'http://a/bb/ccc/g#s');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g?y#s', 'http://a/bb/ccc/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/..', ';x', 'http://a/bb/ccc/;x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g;x', 'http://a/bb/ccc/g;x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g;x?y#s', 'http://a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/..', '', 'http://a/bb/ccc/..');
        $this->itShouldResolve('http://a/bb/ccc/..', '.', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/..', './', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/..', '..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/..', '../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/..', '../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/..', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/..', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/..', '../../g', 'http://a/g');

        // RFC3986 abnormal examples with trailing /.. in the base IRI
        $this->itShouldResolve('http://a/bb/ccc/..', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/..', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/..', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/..', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g.', 'http://a/bb/ccc/g.');
        $this->itShouldResolve('http://a/bb/ccc/..', '.g', 'http://a/bb/ccc/.g');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g..', 'http://a/bb/ccc/g..');
        $this->itShouldResolve('http://a/bb/ccc/..', '..g', 'http://a/bb/ccc/..g');
        $this->itShouldResolve('http://a/bb/ccc/..', './../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/..', './g/.', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g/./h', 'http://a/bb/ccc/g/h');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g/../h', 'http://a/bb/ccc/h');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g;x=1/./y', 'http://a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g;x=1/../y', 'http://a/bb/ccc/y');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g?y/./x', 'http://a/bb/ccc/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g?y/../x', 'http://a/bb/ccc/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g#s/./x', 'http://a/bb/ccc/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'g#s/../x', 'http://a/bb/ccc/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/..', 'http:g', 'http:g');

        // RFC3986 normal examples with fragment in base IRI
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g:h', 'g:h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', './g', 'http://a/bb/ccc/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g/', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '/g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '//g', 'http://g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '?y', 'http://a/bb/ccc/d;p?y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g?y', 'http://a/bb/ccc/g?y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '#s', 'http://a/bb/ccc/d;p?q#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g#s', 'http://a/bb/ccc/g#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g?y#s', 'http://a/bb/ccc/g?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', ';x', 'http://a/bb/ccc/;x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g;x', 'http://a/bb/ccc/g;x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g;x?y#s', 'http://a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '', 'http://a/bb/ccc/d;p?q');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '.', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', './', 'http://a/bb/ccc/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '..', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../', 'http://a/bb/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../..', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../../', 'http://a/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../../g', 'http://a/g');

        // RFC3986 abnormal examples with fragment in base IRI
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '../../../../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '/./g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '/../g', 'http://a/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g.', 'http://a/bb/ccc/g.');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '.g', 'http://a/bb/ccc/.g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g..', 'http://a/bb/ccc/g..');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', '..g', 'http://a/bb/ccc/..g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', './../g', 'http://a/bb/g');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', './g/.', 'http://a/bb/ccc/g/');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g/./h', 'http://a/bb/ccc/g/h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g/../h', 'http://a/bb/ccc/h');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g;x=1/./y', 'http://a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g;x=1/../y', 'http://a/bb/ccc/y');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g?y/./x', 'http://a/bb/ccc/g?y/./x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g?y/../x', 'http://a/bb/ccc/g?y/../x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g#s/./x', 'http://a/bb/ccc/g#s/./x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'g#s/../x', 'http://a/bb/ccc/g#s/../x');
        $this->itShouldResolve('http://a/bb/ccc/d;p?q#f', 'http:g', 'http:g');

        // RFC3986 normal examples with file path
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g:h', 'g:h');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g', 'file:///a/bb/ccc/g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', './g', 'file:///a/bb/ccc/g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g/', 'file:///a/bb/ccc/g/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '/g', 'file:///g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '//g', 'file://g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '?y', 'file:///a/bb/ccc/d;p?y');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g?y', 'file:///a/bb/ccc/g?y');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '#s', 'file:///a/bb/ccc/d;p?q#s');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g#s', 'file:///a/bb/ccc/g#s');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g?y#s', 'file:///a/bb/ccc/g?y#s');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', ';x', 'file:///a/bb/ccc/;x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g;x', 'file:///a/bb/ccc/g;x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g;x?y#s', 'file:///a/bb/ccc/g;x?y#s');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '', 'file:///a/bb/ccc/d;p?q');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '.', 'file:///a/bb/ccc/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', './', 'file:///a/bb/ccc/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '..', 'file:///a/bb/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../', 'file:///a/bb/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../g', 'file:///a/bb/g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../..', 'file:///a/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../../', 'file:///a/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../../g', 'file:///a/g');

        // RFC3986 abnormal examples with file path
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../../../g', 'file:///g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '../../../../g', 'file:///g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '/./g', 'file:///g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '/../g', 'file:///g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g.', 'file:///a/bb/ccc/g.');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '.g', 'file:///a/bb/ccc/.g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g..', 'file:///a/bb/ccc/g..');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', '..g', 'file:///a/bb/ccc/..g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', './../g', 'file:///a/bb/g');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', './g/.', 'file:///a/bb/ccc/g/');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g/./h', 'file:///a/bb/ccc/g/h');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g/../h', 'file:///a/bb/ccc/h');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g;x=1/./y', 'file:///a/bb/ccc/g;x=1/y');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g;x=1/../y', 'file:///a/bb/ccc/y');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g?y/./x', 'file:///a/bb/ccc/g?y/./x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g?y/../x', 'file:///a/bb/ccc/g?y/../x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g#s/./x', 'file:///a/bb/ccc/g#s/./x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'g#s/../x', 'file:///a/bb/ccc/g#s/../x');
        $this->itShouldResolve('file:///a/bb/ccc/d;p?q', 'http:g', 'http:g');

        // additional cases
        // relative paths ending with '.'
        $this->itShouldResolve('http://abc/', '.', 'http://abc/');
        $this->itShouldResolve('http://abc/def/ghi', '.', 'http://abc/def/');
        $this->itShouldResolve('http://abc/def/ghi', '.?a=b', 'http://abc/def/?a=b');
        $this->itShouldResolve('http://abc/def/ghi', '.#a=b', 'http://abc/def/#a=b');

        // relative paths ending with '..'
        $this->itShouldResolve('http://abc/', '..', 'http://abc/');
        $this->itShouldResolve('http://abc/def/ghi', '..', 'http://abc/');
        $this->itShouldResolve('http://abc/def/ghi', '..?a=b', 'http://abc/?a=b');
        $this->itShouldResolve('http://abc/def/ghi', '..#a=b', 'http://abc/#a=b');

        // base path with empty subpaths (double slashes)
        $this->itShouldResolve('http://ab//de//ghi', 'xyz', 'http://ab//de//xyz');
        $this->itShouldResolve('http://ab//de//ghi', './xyz', 'http://ab//de//xyz');
        $this->itShouldResolve('http://ab//de//ghi', '../xyz', 'http://ab//de/xyz');

        // base path with colon (possible confusion with scheme)
        $this->itShouldResolve('http://abc/d:f/ghi', 'xyz', 'http://abc/d:f/xyz');
        $this->itShouldResolve('http://abc/d:f/ghi', './xyz', 'http://abc/d:f/xyz');
        $this->itShouldResolve('http://abc/d:f/ghi', '../xyz', 'http://abc/xyz');

        // base path consisting of '..' and/or '../' sequences
        $this->itShouldResolve('./', 'abc', '/abc');
        $this->itShouldResolve('../', 'abc', '/abc');
        $this->itShouldResolve('./././', '././abc', '/abc');
        $this->itShouldResolve('../../../', '../../abc', '/abc');
        $this->itShouldResolve('.../././', '././abc', '.../abc');

        // base path without authority
        $this->itShouldResolve('a:b:c/', 'def/../', 'a:b:c/');
        $this->itShouldResolve('a:b:c', '/def', 'a:/def');
        $this->itShouldResolve('a:b/c', '/def', 'a:/def');
        $this->itShouldResolve('a:', '/.', 'a:/');
        $this->itShouldResolve('a:', '/..', 'a:/');

        // base path with slashes in query string
        $this->itShouldResolve('http://abc/def/ghi?q=xx/yyy/z', 'jjj', 'http://abc/def/jjj');
        $this->itShouldResolve('http://abc/def/ghi?q=xx/y?y/z', 'jjj', 'http://abc/def/jjj');
    }
}
