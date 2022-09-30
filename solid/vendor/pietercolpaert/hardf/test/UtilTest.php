<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\Util;

class UtilTest extends TestCase
{
    public function testIsIRI(): void
    {
        $this->assertIsBool(Util::isIRI('http://test.be'));
        $this->assertTrue(
            Util::isIRI('http://test.be')
        );
        $this->assertFalse(
            Util::isIRI('"http://test.be"')
        );
        //Does not match a blank node
        $this->assertFalse(
            Util::isIRI('_:A')
        );
        $this->assertFalse(Util::isIRI(null));
    }

    public function testIsLiteral(): void
    {
        $this->assertTrue(Util::isLiteral('"http://example.org/"'));
        $this->assertTrue(Util::isLiteral('"English"@en'));
        // it matches a literal with a language that contains a number
        $this->assertTrue(Util::isLiteral('"English"@es-419'));
        // it matches a literal with a type
        $this->assertTrue(Util::isLiteral('"3"^^http://www.w3.org/2001/XMLSchema#integer'));
        // it matches a literal with a newline
        $this->assertTrue(Util::isLiteral('"a\nb"'));
        // it matches a literal with a cariage return
        $this->assertTrue(Util::isLiteral('"a\rb"'));
        // it does not match an IRI
        $this->assertFalse(Util::isLiteral('http://example.org/'));
        // it does not match a blank node
        $this->assertFalse(Util::isLiteral('_:x'));
        // it does not match null
        $this->assertFalse(Util::isLiteral(null));
    }

    public function testIsBlank(): void
    {
        // it matches a blank node
        $this->assertTrue(Util::isBlank('_:x'));
        // it does not match an IRI
        $this->assertFalse(Util::isBlank('http://example.org/'));
        // it does not match a literal
        $this->assertFalse(Util::isBlank('"http://example.org/"'));
        $this->assertFalse(Util::isBlank(null));
    }

    public function testIsDefaultGraph(): void
    {
        $this->assertFalse(Util::isDefaultGraph('_:x'));
        $this->assertFalse(Util::isDefaultGraph('http://example.org/'));
        $this->assertFalse(Util::isDefaultGraph('"http://example.org/"'));
        // it matches null
        $this->assertTrue(Util::isDefaultGraph(null));
        // it matches the empty string
        $this->assertTrue(Util::isDefaultGraph(''));
    }

    public function testinDefaultGraph(): void
    {
        // it does not match a blank node
        $this->assertFalse(Util::inDefaultGraph(['graph' => '_:x']));
        // it does not match an IRI
        $this->assertFalse(Util::inDefaultGraph(['graph' => 'http://example.org/']));
        // it does not match a literal
        $this->assertFalse(Util::inDefaultGraph(['graph' => '"http://example.org/"']));
        // it matches null
        $this->assertTrue(Util::inDefaultGraph(['graph' => null]));
        // it matches the empty string
        $this->assertTrue(Util::inDefaultGraph(['graph' => '']));
    }

    public function testGetLiteralValue(): void
    {
        // it gets the value of a literal
        $this->assertEquals('Mickey', Util::getLiteralValue('"Mickey"'));

        // it gets the value of a literal with a language
        $this->assertEquals('English', Util::getLiteralValue('"English"@en'));

        // it gets the value of a literal with a language that contains a number
        $this->assertEquals('English', Util::getLiteralValue('"English"@es-419'));

        // it gets the value of a literal with a type
        $this->assertEquals('3', Util::getLiteralValue('"3"^^http://www.w3.org/2001/XMLSchema#integer'));

        // it gets the value of a literal with a newline
        $this->assertEquals('Mickey\nMouse', Util::getLiteralValue('"Mickey\nMouse"'));

        // it gets the value of a literal with a cariage return
        $this->assertEquals('Mickey\rMouse', Util::getLiteralValue('"Mickey\rMouse"'));

        $this->assertEquals("foo\nbar", Util::getLiteralValue('"' . "foo\nbar" . '"'));

        // it does not work with non-literals
        //TODO: Util::getLiteralValue.bind(null, 'http://ex.org/').should.throw('http://ex.org/ is not a literal');

        // it does not work with null
        //TODO: Util::getLiteralValue.bind(null, null).should.throw('null is not a literal');
    }

    // tests reaction if no literal was given
    public function testGetLiteralValueNoLiteralGiven(): void
    {
        $this->expectException('\Exception');

        Util::getLiteralValue('invalid');
    }

    public function testGetLiteralType(): void
    {
        // it gets the type of a literal
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#string', Util::getLiteralType('"Mickey"'));

        // it gets the type of a literal with a language
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', Util::getLiteralType('"English"@en'));

        // it gets the type of a literal with a language that contains a number
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', Util::getLiteralType('"English"@es-419'));

        // it gets the type of a literal with a type
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#integer', Util::getLiteralType('"3"^^http://www.w3.org/2001/XMLSchema#integer'));

        // it gets the type of a literal with a newline
        $this->assertEquals('abc', Util::getLiteralType('"Mickey\nMouse"^^abc'));

        // it gets the type of a literal with a cariage return
        $this->assertEquals('abc', Util::getLiteralType('"Mickey\rMouse"^^abc'));

        // it does not work with non-literals
        //TODO: Util::getLiteralType.bind(null, 'http://example.org/').should.throw('http://example.org/ is not a literal');

        // it does not work with null
        //TODO: Util::getLiteralType.bind(null, null).should.throw('null is not a literal');
    }

    // tests getLiteralType if multi line string was given (check for adaption of Util.php,
    // adding an s to the regex)
    public function testGetLiteralTypeMultilineString(): void
    {
        $literal = '"This document is published by the Provenance Working Group (http://www.w3.org/2011/prov/wiki/Main_Page).

If you wish to make comments regarding this document, please send them to public-prov-comments@w3.org (subscribe public-prov-comments-request@w3.org, archives http://lists.w3.org/Archives/Public/public-prov-comments/). All feedback is welcome."^^<http://>';

        $this->assertEquals('<http://>', Util::getLiteralType($literal));
    }

    public function testGetLiteralLanguage(): void
    {
        // it gets the language of a literal
        $this->assertEquals('', Util::getLiteralLanguage('"Mickey"'));

        // it gets the language of a literal with a language
        $this->assertEquals('en', Util::getLiteralLanguage('"English"@en'));

        // it gets the language of a literal with a language that contains a number
        $this->assertEquals('es-419', Util::getLiteralLanguage('"English"@es-419'));

        // it normalizes the language to lowercase
        $this->assertEquals('en-gb', Util::getLiteralLanguage('"English"@en-GB'));

        // it gets the language of a literal with a type
        $this->assertEquals('', Util::getLiteralLanguage('"3"^^http://www.w3.org/2001/XMLSchema#integer'));

        // it gets the language of a literal with a newline
        $this->assertEquals('en', Util::getLiteralLanguage('"Mickey\nMouse"@en'));

        // it gets the language of a literal with a cariage return
        $this->assertEquals('en', Util::getLiteralLanguage('"Mickey\rMouse"@en'));
    }

    // tests getLiteralLanguage if multi line string was given (check for adaption of Util.php,
    // adding an s to the regex)
    public function testGetLiteralLanguageMultilineString(): void
    {
        $literal = '"This document is published by the Provenance Working Group (http://www.w3.org/2011/prov/wiki/Main_Page).

If you wish to make comments regarding this document, please send them to public-prov-comments@w3.org (subscribe public-prov-comments-request@w3.org, archives http://lists.w3.org/Archives/Public/public-prov-comments/). All feedback is welcome."@en';

        $this->assertEquals('en', Util::getLiteralLanguage($literal));
    }

    // tests reaction if no language was given
    public function testGetLiteralLanguageNoLiteralGiven(): void
    {
        $this->expectException('\Exception');

        Util::getLiteralLanguage('invalid');
    }

    public function testIsPrefixedName(): void
    {
        // it matches a prefixed name
        $this->assertTrue(Util::isPrefixedName('ex:Test'));

        // it does not match an IRI
        $this->assertFalse(Util::isPrefixedName('http://example.org/'));

        // it does not match a literal
        $this->assertFalse(Util::isPrefixedName('"http://example.org/"'));

        // it does not match a literal with a colon
        $this->assertFalse(Util::isPrefixedName('"a:b"'));

        // it does not match null
        $this->assertFalse(Util::isPrefixedName(null));
    }

    public function testExpandPrefixedName(): void
    {
        // it expands a prefixed name
        $this->assertEquals('http://ex.org/#Test', Util::expandPrefixedName('ex:Test', ['ex' => 'http://ex.org/#']));
        // it expands a type with a prefixed name
        $this->assertEquals('"a"^^http://ex.org/#type', Util::expandPrefixedName('"a"^^ex:type', ['ex' => 'http://ex.org/#']));
        // it expands a prefixed name with the empty prefix
        $this->assertEquals('http://ex.org/#Test', Util::expandPrefixedName(':Test', ['' => 'http://ex.org/#']));
        // it does not expand a prefixed name if the prefix is unknown
        $this->assertEquals('a:Test', Util::expandPrefixedName('a:Test', ['b' => 'http://ex.org/#']));
        // it returns the input if //it is not a prefixed name
        $this->assertEquals('abc', Util::expandPrefixedName('abc', null));
    }

    public function testCreateIRI(): void
    {
        // it converts a plain IRI
        $this->assertEquals('http://ex.org/foo#bar', Util::createIRI('http://ex.org/foo#bar'));

        // it converts a literal
        $this->assertEquals('http://ex.org/foo#bar', Util::createIRI('"http://ex.org/foo#bar"^^uri:type'));

        // it converts null
        $this->assertNull(Util::createIRI(null));
    }

    public function testCreateLiteral(): void
    {
        // it converts the empty string
        $this->assertEquals('""', Util::createLiteral(''));

        // it converts the empty string with a language
        $this->assertEquals('""@en-gb', Util::createLiteral('', 'en-GB'));

        // it converts the empty string with a type
        $this->assertEquals('""^^http://ex.org/type', Util::createLiteral('', 'http://ex.org/type'));

        // it converts a non-empty string
        $this->assertEquals('"abc"', Util::createLiteral('abc'));

        // it converts a non-empty string with a language
        $this->assertEquals('"abc"@en-gb', Util::createLiteral('abc', 'en-GB'));

        // it converts a non-empty string with a type
        $this->assertEquals('"abc"^^http://ex.org/type', Util::createLiteral('abc', 'http://ex.org/type'));

        // it converts an integer
        $this->assertEquals('"123"^^http://www.w3.org/2001/XMLSchema#integer', Util::createLiteral(123));

        // it converts a decimal
        $this->assertEquals('"2.3"^^http://www.w3.org/2001/XMLSchema#double', Util::createLiteral(2.3));

        // it converts infinity
        $this->assertEquals('"INF"^^http://www.w3.org/2001/XMLSchema#double', Util::createLiteral(INF));

        // it converts false
        $this->assertEquals('"false"^^http://www.w3.org/2001/XMLSchema#boolean', Util::createLiteral(false));

        // it converts true
        $this->assertEquals('"true"^^http://www.w3.org/2001/XMLSchema#boolean', Util::createLiteral(true));
    }

    /*
      public function testprefix () {
      var baz = Util::prefix('http://ex.org/baz#');
      // it should return a function
      $this->assertEquals(an.instanceof(Function), baz);

      }
      public function testthe function () {
      // it should expand the prefix
      expect(baz('bar')).to.equal('http://ex.org/baz#bar');

      }
    */
/*
  public function testprefixes () {
  public function testCalled without arguments () {
  var prefixes = Util::prefixes();
  // it should return a function
  $this->assertEquals(an.instanceof(Function), prefixes);


  public function testthe function () {
  // it should not expand non-registered prefixes
  expect(prefixes('baz')('bar')).to.equal('bar');


  // it should allow registering prefixes
  var p = prefixes('baz', 'http://ex.org/baz#');
  expect(p).to.exist;
  expect(p).to.equal(prefixes('baz'));


  // it should expand the newly registered prefix
  expect(prefixes('baz')('bar')).to.equal('http://ex.org/baz#bar');


  }*/
/*
    public function testCalled with a hash of prefixes () {
        var prefixes = Util::prefixes({ foo: 'http://ex.org/foo#', bar: 'http://ex.org/bar#'
                // it should return a function
                $this->assertEquals(an.instanceof(Function), prefixes);


            public function testthe function () {
                // it should expand registered prefixes
                expect(prefixes('foo')('bar')).to.equal('http://ex.org/foo#bar');
                expect(prefixes('bar')('bar')).to.equal('http://ex.org/bar#bar');


                // it should not expand non-registered prefixes
                expect(prefixes('baz')('bar')).to.equal('bar');


                // it should allow registering prefixes
                var p = prefixes('baz', 'http://ex.org/baz#');
                expect(p).to.exist;
                expect(p).to.equal(prefixes('baz'));


                // it should expand the newly registered prefix
                expect(prefixes('baz')('bar')).to.equal('http://ex.org/baz#bar');


            }
*/
}
