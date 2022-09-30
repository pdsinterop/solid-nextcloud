<?php

namespace Tests\hardf;

use Exception;
use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGWriter;

class TriGWriterTest extends TestCase
{
    private function shouldSerialize(): void
    {
        $numargs = \func_num_args();
        $expectedResult = func_get_arg($numargs - 1);
        $i = 0;
        $prefixes = [];
        if (0 !== func_get_arg($i) && isset(func_get_arg($i)['prefixes'])) {
            $prefixes = func_get_arg($i)['prefixes'];
            ++$i;
        }
        $writer = new TrigWriter(['prefixes' => $prefixes]);
        for ($i; $i < $numargs - 1; ++$i) {
            /**
             * @var array<int, string>
             */
            $item = func_get_arg($i);

            /**
             * @var string|null
             */
            $g = isset($item[3]) ? $item[3] : null;

            $writer->addTriple(['subject' => $item[0], 'predicate' => $item[1], 'object' => $item[2], 'graph' => $g]);
        }
        $output = $writer->end();

        $this->assertEquals($expectedResult, $output);
    }

    private function shouldNotSerialize(): void
    {
        $numargs = \func_num_args();
        $errorMessage = func_get_arg($numargs - 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($errorMessage);

        $writer = new TrigWriter();
        for ($i = 0; $i < $numargs - 1; ++$i) {
            /**
             * @var array<int, string>
             */
            $item = func_get_arg($i);

            /**
             * @var string|null
             */
            $g = isset($item[3]) ? $item[3] : null;

            $writer->addTriple(['subject' => $item[0], 'predicate' => $item[1], 'object' => $item[2], 'graph' => $g]);
        }
        $output = $writer->end();
    }

    public function testZeroOrMoreTriples(): void
    {
        //should serialize 0 triples',
        $this->shouldSerialize('');
        //should serialize 1 triple',
        $this->shouldSerialize(['abc', 'def', 'ghi'],
        '<abc> <def> <ghi>.'."\n");

        //should serialize 2 triples',
        $this->shouldSerialize(['abc', 'def', 'ghi'],
        ['jkl', 'mno', 'pqr'],
        '<abc> <def> <ghi>.'."\n".
        '<jkl> <mno> <pqr>.'."\n");

        //should serialize 3 triples',
        $this->shouldSerialize(['abc', 'def', 'ghi'],
        ['jkl', 'mno', 'pqr'],
        ['stu', 'vwx', 'yz'],
        '<abc> <def> <ghi>.'."\n".
        '<jkl> <mno> <pqr>.'."\n".
        '<stu> <vwx> <yz>.'."\n");
    }

    public function testLiterals(): void
    {
        //should serialize a literal',
        $this->shouldSerialize(['a', 'b', '"cde"'],
        '<a> <b> "cde".'."\n");

        //should serialize a literal with a type',
        $this->shouldSerialize(['a', 'b', '"cde"^^fgh'],
        '<a> <b> "cde"^^<fgh>.'."\n");

        //should serialize a literal with a language',
        $this->shouldSerialize(['a', 'b', '"cde"@en-us'],
        '<a> <b> "cde"@en-us.'."\n");

        //should serialize a literal containing a single quote',
        $this->shouldSerialize(['a', 'b', '"c\'de"'],
        '<a> <b> "c\'de".'."\n");

        //should serialize a literal containing a double quote',
        $this->shouldSerialize(['a', 'b', '"c"de"'],
        '<a> <b> "c\\"de".'."\n");

        //should serialize a literal containing a backslash'
        $this->shouldSerialize(['a', 'b', '"c\\de"'],
        '<a> <b> "c\\\\de".'."\n");

        //should serialize a literal containing a tab character',
        $this->shouldSerialize(['a', 'b', "\"c\tde\""],
        "<a> <b> \"c\\tde\".\n");

        //should serialize a literal containing a newline character',
        /*      shouldSerialize(['a', 'b', '"c\nde"'],
                      '<a> <b> "c\\nde".\n'));*/
        $this->shouldSerialize(['a', 'b', '"c'."\n".'de"'],
        '<a> <b> "c\\nde".'."\n");

        //should serialize a literal containing a cariage return character',
        $this->shouldSerialize(['a', 'b', '"c'."\r".'de"'],
        '<a> <b> "c\\rde".'."\n");

        //should serialize a literal containing a backspace character',
        $this->shouldSerialize(['a', 'b', '"c'.\chr(8).'de"'],
          '<a> <b> "'."c\bde".'".'."\n"); //→ TODO: Doesn’t work properly

        //should serialize a literal containing a form feed character',
        $this->shouldSerialize(['a', 'b', '"c'."\f".'de"'],
        '<a> <b> "c\\fde".'."\n");

        //should serialize a literal containing a line separator
        $this->shouldSerialize(['a', 'b', "\"c\u{2028}de\""], '<a> <b> "c'."\u{2028}".'de".'."\n");
    }

    public function testBlankNodes(): void
    {
        //should serialize blank nodes',
        $this->shouldSerialize(['_:a', 'b', '_:c'],
        '_:a <b> _:c.'."\n");
    }

    public function testWrongLiterals(): void
    {
        //should not serialize a literal in the subject',
        $this->shouldNotSerialize(['"a"', 'b', '"c"'],
        'A literal as subject is not allowed: "a"');

        //should not serialize a literal in the predicate',
        $this->shouldNotSerialize(['a', '"b"', '"c"'],
        'A literal as predicate is not allowed: "b"');

        //should not serialize an invalid object literal',
        $this->shouldNotSerialize(['a', 'b', '"c'],
        'Invalid literal: "c');
    }

    public function testPrefixes(): void
    {
        //should not leave leading whitespace if the prefix set is empty',
        $this->shouldSerialize(['prefixes' => []],
        ['a', 'b', 'c'],
        '<a> <b> <c>.'."\n");

        //should serialize valid prefixes',
        $this->shouldSerialize(['prefixes' => ['a' => 'http://a.org/', 'b' => 'http://a.org/b#', 'c' => 'http://a.org/b']],
        '@prefix a: <http://a.org/>.'."\n".
        '@prefix b: <http://a.org/b#>.'."\n"."\n");

        //should use prefixes when possible',
        $this->shouldSerialize(['prefixes' => ['a' => 'http://a.org/', 'b' => 'http://a.org/b#', 'c' => 'http://a.org/b']],
        ['http://a.org/bc', 'http://a.org/b#ef', 'http://a.org/bhi'],
        ['http://a.org/bc/de', 'http://a.org/b#e#f', 'http://a.org/b#x/t'],
        ['http://a.org/3a', 'http://a.org/b#3a', 'http://a.org/b#a3'],
        '@prefix a: <http://a.org/>.'."\n".
        '@prefix b: <http://a.org/b#>.'."\n"."\n".
        'a:bc b:ef a:bhi.'."\n".
        '<http://a.org/bc/de> <http://a.org/b#e#f> <http://a.org/b#x/t>.'."\n".
        '<http://a.org/3a> <http://a.org/b#3a> b:a3.'."\n");

        //should expand prefixes when possible',
        $this->shouldSerialize(['prefixes' => ['a' => 'http://a.org/', 'b' => 'http://a.org/b#']],
        ['a:bc', 'b:ef', 'c:bhi'],
        '@prefix a: <http://a.org/>.'."\n".
        '@prefix b: <http://a.org/b#>.'."\n"."\n".
        'a:bc b:ef <c:bhi>.'."\n");
    }

    public function testRepitition(): void
    {
        //should not repeat the same subjects',
        $this->shouldSerialize(['abc', 'def', 'ghi'],
        ['abc', 'mno', 'pqr'],
        ['stu', 'vwx', 'yz'],
        '<abc> <def> <ghi>;'."\n".
        '    <mno> <pqr>.'."\n".
        '<stu> <vwx> <yz>.'."\n");

        //should not repeat the same predicates',
        $this->shouldSerialize(['abc', 'def', 'ghi'],
        ['abc', 'def', 'pqr'],
        ['abc', 'bef', 'ghi'],
        ['abc', 'bef', 'pqr'],
        ['stu', 'bef', 'yz'],
        '<abc> <def> <ghi>, <pqr>;'."\n".
        '    <bef> <ghi>, <pqr>.'."\n".
        '<stu> <bef> <yz>.'."\n");
    }

    public function testRdfType(): void
    {
        //should write rdf:type as "a"',
        $this->shouldSerialize(['abc', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'def'],
        '<abc> a <def>.'."\n");
    }

    public function testQuads(): void
    {
        //should serialize a graph with 1 triple',
        $this->shouldSerialize(['abc', 'def', 'ghi', 'xyz'],
        '<xyz> {'."\n".
        '<abc> <def> <ghi>'."\n".
        '}'."\n");

        //should serialize a graph with 3 triples',
        $this->shouldSerialize(['abc', 'def', 'ghi', 'xyz'],
        ['jkl', 'mno', 'pqr', 'xyz'],
        ['stu', 'vwx', 'yz',  'xyz'],
        '<xyz> {'."\n".
        '<abc> <def> <ghi>.'."\n".
        '<jkl> <mno> <pqr>.'."\n".
        '<stu> <vwx> <yz>'."\n".
        '}'."\n");

        //should serialize three graphs',
        $this->shouldSerialize(['abc', 'def', 'ghi', 'xyz'],
        ['jkl', 'mno', 'pqr', ''],
        ['stu', 'vwx', 'yz',  'abc'],
        '<xyz> {'."\n".'<abc> <def> <ghi>'."\n".'}'."\n".
        '<jkl> <mno> <pqr>.'."\n".
        '<abc> {'."\n".'<stu> <vwx> <yz>'."\n".'}'."\n");
    }

    public function testCallbackOnEnd(): void
    {
        //sends output through end
        $writer = new TriGWriter();
        $writer->addTriple(['subject' => 'a', 'predicate' => 'b', 'object' => 'c']);
        $output = $writer->end();
        $this->assertEquals("<a> <b> <c>.\n", $output);
    }

    public function testRespectingPrefixes(): void
    {
        //respects the prefixes argument when no stream argument is given', function (done) {
        $writer = new TriGWriter(['prefixes' => ['a' => 'b#']]);
        $writer->addTriple(['subject' => 'b#a', 'predicate' => 'b#b', 'object' => 'b#c']);
        $output = $writer->end();
        $this->assertEquals("@prefix a: <b#>.\n\na:a a:b a:c.\n", $output);
    }

    public function testOtherPrefixes(): void
    {
        //does not repeat identical prefixes', function (done) {
        $writer = new TriGWriter();
        $writer->addPrefix('a', 'b#');
        $writer->addPrefix('a', 'b#');
        $writer->addTriple(['subject' => 'b#a', 'predicate' => 'b#b', 'object' => 'b#c']);
        $writer->addPrefix('a', 'b#');
        $writer->addPrefix('a', 'b#');
        $writer->addPrefix('b', 'b#');
        $writer->addPrefix('a', 'c#');
        $output = $writer->end();
        $this->assertEquals('@prefix a: <b#>.'."\n"."\n".'a:a a:b a:c.'."\n".'@prefix b: <b#>.'."\n"."\n".'@prefix a: <c#>.'."\n"."\n", $output);

        //serializes triples of a graph with a prefix declaration in between', function (done) {
        $writer = new TriGWriter();
        $writer->addPrefix('a', 'b#');
        $writer->addTriple(['subject' => 'b#a', 'predicate' => 'b#b', 'object' => 'b#c', 'graph' => 'b#g']);
        $writer->addPrefix('d', 'e#');
        $writer->addTriple(['subject' => 'b#a', 'predicate' => 'b#b', 'object' => 'b#d', 'graph' => 'b#g']);
        $output = $writer->end();
        $this->assertEquals('@prefix a: <b#>.'."\n"."\n".'a:g {'."\n".'a:a a:b a:c'."\n".'}'."\n".'@prefix d: <e#>.'."\n"."\n".'a:g {'."\n".'a:a a:b a:d'."\n".'}'."\n", $output);

        //should accept triples with separated components', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', 'c');
        $writer->addTriple('a', 'b', 'd');
        $output = $writer->end();
        $this->assertEquals('<a> <b> <c>, <d>.'."\n", $output);

        //should accept quads with separated components', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', 'c', 'g');
        $writer->addTriple('a', 'b', 'd', 'g');
        $output = $writer->end();
        $this->assertEquals('<g> {'."\n".'<a> <b> <c>, <d>'."\n".'}'."\n", $output);
    }

    public function testBlankNodes2(): void
    {
        //should serialize triples with an empty blank node as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->blank());
        $writer->addTriple('a2', 'b', $writer->blank([]));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> [].'."\n".'<a2> <b> [].'."\n", $output);

        //should serialize triples with a one-triple blank node as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->blank('d', 'e'));
        $writer->addTriple('a2', 'b', $writer->blank(['predicate' => 'd', 'object' => 'e']));
        $writer->addTriple('a3', 'b', $writer->blank([['predicate' => 'd', 'object' => 'e']]));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> [ <d> <e> ].'."\n".'<a2> <b> [ <d> <e> ].'."\n".'<a3> <b> [ <d> <e> ].'."\n", $output);

        //should serialize triples with a two-triple blank node as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => 'e'],
            ['predicate' => 'f', 'object' => '"g"'],
        ]));
        $output = $writer->end();
        $this->assertEquals('<a> <b> ['."\n".'  <d> <e>;'."\n".'  <f> "g"'."\n".'].'."\n", $output);

        //should serialize triples with a three-triple blank node as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => 'e'],
            ['predicate' => 'f', 'object' => '"g"'],
            ['predicate' => 'h', 'object' => 'i'],
        ]));
        $output = $writer->end();
        $this->assertEquals('<a> <b> ['."\n".'  <d> <e>;'."\n".'  <f> "g";'."\n".'  <h> <i>'."\n".'].'."\n", $output);

        //should serialize triples with predicate-sharing blank node triples as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => 'e'],
            ['predicate' => 'd', 'object' => 'f'],
            ['predicate' => 'g', 'object' => 'h'],
            ['predicate' => 'g', 'object' => 'i'],
        ]));
        $output = $writer->end();
        $this->assertEquals('<a> <b> ['."\n".'  <d> <e>, <f>;'."\n".'  <g> <h>, <i>'."\n".'].'."\n", $output);

        //should serialize triples with nested blank nodes as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => $writer->blank()],
        ]));
        $writer->addTriple('a2', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => $writer->blank('e', 'f')],
            ['predicate' => 'g', 'object' => $writer->blank('h', '"i"')],
        ]));
        $writer->addTriple('a3', 'b', $writer->blank([
            ['predicate' => 'd', 'object' => $writer->blank([
                ['predicate' => 'g', 'object' => $writer->blank('h', 'i')],
                ['predicate' => 'j', 'object' => $writer->blank('k', '"l"')],
            ])],
        ]));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> ['."\n".'  <d> []'."\n".'].'."\n".'<a2> <b> ['."\n".'  <d> [ <e> <f> ];'."\n".'  <g> [ <h> "i" ]'."\n".'].'."\n".'<a3> <b> ['."\n".'  <d> ['."\n".'  <g> [ <h> <i> ];'."\n".'  <j> [ <k> "l" ]'."\n".']'."\n".'].'."\n", $output);

        //should serialize triples with an empty blank node as subject', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple($writer->blank(), 'b', 'c');
        $writer->addTriple($writer->blank([]), 'b', 'c');
        $output = $writer->end();
        $this->assertEquals('[] <b> <c>.'."\n".'[] <b> <c>.'."\n", $output);

        //should serialize triples with a one-triple blank node as subject', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple($writer->blank('a', 'b'), 'c', 'd');
        $writer->addTriple($writer->blank(['predicate' => 'a', 'object' => 'b']), 'c', 'd');
        $writer->addTriple($writer->blank([['predicate' => 'a', 'object' => 'b']]), 'c', 'd');
        $output = $writer->end();
        $this->assertEquals(
            '[ <a> <b> ] <c> <d>.'."\n".'[ <a> <b> ] <c> <d>.'."\n".'[ <a> <b> ] <c> <d>.'."\n",
            $output
        );

        //should serialize triples with an empty blank node as graph', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', 'c', $writer->blank());
        $writer->addTriple('a', 'b', 'c', $writer->blank([]));
        $output = $writer->end();
        $this->assertEquals(
            '[] {'."\n".'<a> <b> <c>'."\n".'}'."\n".'[] {'."\n".'<a> <b> <c>'."\n".'}'."\n",
            $output
        );
    }

    public function testLists(): void
    {
        //should serialize triples with an empty list as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->addList());
        $writer->addTriple('a2', 'b', $writer->addList([]));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> ().'.PHP_EOL.'<a2> <b> ().'.PHP_EOL, $output);

        //should serialize triples with a one-element list as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->addList(['c']));
        $writer->addTriple('a2', 'b', $writer->addList(['"c"']));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> (<c>).'."\n".'<a2> <b> ("c").'."\n", $output);

        //should serialize triples with a three-element list as object', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple('a1', 'b', $writer->addList(['c', 'd', 'e']));
        $writer->addTriple('a2', 'b', $writer->addList(['"c"', '"d"', '"e"']));
        $output = $writer->end();
        $this->assertEquals('<a1> <b> (<c> <d> <e>).'."\n".'<a2> <b> ("c" "d" "e").'."\n", $output);

        //should serialize triples with an empty list as subject', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple($writer->addList(), 'b1', 'c');
        $writer->addTriple($writer->addList([]), 'b2', 'c');
        $output = $writer->end();
        $this->assertEquals('() <b1> <c>;'."\n".'    <b2> <c>.'."\n", $output);

        //should serialize triples with a one-element list as subject', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple($writer->addList(['a']), 'b1', 'c');
        $writer->addTriple($writer->addList(['a']), 'b2', 'c');
        $output = $writer->end();
        $this->assertEquals('(<a>) <b1> <c>;'."\n".'    <b2> <c>.'."\n", $output);

        //should serialize triples with a three-element list as subject', function (done) {
        $writer = new TriGWriter();
        $writer->addTriple($writer->addList(['a', '"b"', '"c"']), 'd', 'e');
        $output = $writer->end();
        $this->assertEquals('(<a> "b" "c") <d> <e>.'."\n", $output);
    }

    public function testPartialRead(): void
    {
        //should only partially output the already given data and then continue writing until end
        $writer = new TriGWriter();
        $writer->addTriple($writer->addList(['a', '"b"', '"c"']), 'd', 'e');
        $output = $writer->read();
        $this->assertEquals('(<a> "b" "c") <d> <e>', $output);

        $writer->addTriple('a', 'b', 'c');
        $output = $writer->end();
        $this->assertEquals(".\n<a> <b> <c>.\n", $output);
    }

    public function testTriplesBulk(): void
    {
        //should accept triples in bulk', function (done) {
        $writer = new TriGWriter();
        $writer->addTriples(
            [
                ['subject' => 'a', 'predicate' => 'b', 'object' => 'c'],
                ['subject' => 'a', 'predicate' => 'b', 'object' => 'd'],
            ]
        );
        $output = $writer->end();
        $this->assertEquals('<a> <b> <c>, <d>.'."\n", $output);
    }

    public function testNTriples(): void
    {
        //should write simple triples in N-Triples mode', function (done) {
        $writer = new TriGWriter(['format' => 'N-Triples']);
        $writer->addTriple('a', 'b', 'c');
        $writer->addTriple('a', 'b', 'd');
        $output = $writer->end();
        $this->assertEquals('<a> <b> <c>.'."\n".'<a> <b> <d>.'."\n", $output);
    }
}
