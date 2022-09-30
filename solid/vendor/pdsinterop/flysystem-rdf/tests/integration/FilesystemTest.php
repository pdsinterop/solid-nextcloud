<?php

namespace Pdsinterop\Rdf\Flysystem\Plugin;

use EasyRdf\Graph as Graph;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Pdsinterop\Rdf\Enum\Format;
use PHPUnit\Framework\TestCase;

class FilesystemTest extends TestCase
{
    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @param $format
     * @param $expected
     *
     * @dataProvider provideFormatResult
     *
     * @coversNothing
     */
    public function test_($format, $expected): void
    {
        $filesystem = new Filesystem(new Local(__DIR__ . '/../fixtures/'));
        $filesystem->addPlugin(new ReadRdf(new Graph()));

        /** @noinspection PhpUndefinedMethodInspection */
        $actual = $filesystem->readRdf('foaf.rdf', $format, 'server');

        self::assertEquals($expected, $actual);
    }

    public function provideFormatResult(): array
    {
        return [
            'json-ld' => [
                Format::JSON_LD,
                <<<'json'
[{"@id":"/"},{"@id":"/inbox/"},{"@id":"/server","@type":["http://xmlns.com/foaf/0.1/PersonalProfileDocument"],"http://xmlns.com/foaf/0.1/maker":[{"@id":"/server#me"}],"http://xmlns.com/foaf/0.1/primaryTopic":[{"@id":"/server#me"}],"http://webns.net/mvcb/generatorAgent":[{"@id":"http://www.ldodds.com/foaf/foaf-a-matic"}],"http://webns.net/mvcb/errorReportsTo":[{"@id":"mailto:leigh@ldodds.com"}]},{"@id":"/server#me","@type":["http://xmlns.com/foaf/0.1/Person"],"http://www.w3.org/ns/ldp#inbox":[{"@id":"/inbox/"}],"http://www.w3.org/ns/solid/terms#account":[{"@id":"/"}],"http://www.w3.org/ns/solid/terms#privateTypeIndex":[{"@id":"/settings/privateTypeIndex.ttl"}],"http://www.w3.org/ns/solid/terms#publicTypeIndex":[{"@id":"/settings/publicTypeIndex.ttl"}],"http://www.w3.org/ns/pim/space#preferencesFile":[{"@id":"/settings/preferencesFile.ttl"}],"http://xmlns.com/foaf/0.1/depiction":[{"@id":"https://www.gravatar.com/avatar/f8d7c4a4899736c59ec1e40c7021d477?s=1024"}],"http://xmlns.com/foaf/0.1/family_name":[{"@value":"Peachey"}],"http://xmlns.com/foaf/0.1/givenname":[{"@value":"Ben"}],"http://xmlns.com/foaf/0.1/homepage":[{"@id":"https://pother.ca"}],"http://xmlns.com/foaf/0.1/knows":[{"@id":"_:b0"},{"@id":"_:b1"}],"http://xmlns.com/foaf/0.1/mbox_sha1sum":[{"@value":"012e360c88b2bf940e6a52de3e5bbf59ccbdada6"}],"http://xmlns.com/foaf/0.1/name":[{"@value":"Ben Peachey"}],"http://xmlns.com/foaf/0.1/nick":[{"@value":"Potherca"}],"http://xmlns.com/foaf/0.1/phone":[{"@id":"tel:0123456789"}],"http://xmlns.com/foaf/0.1/schoolHomepage":[{"@id":"https://aki.artez.nl/"}],"http://xmlns.com/foaf/0.1/title":[{"@value":"Mr."}],"http://xmlns.com/foaf/0.1/workInfoHomepage":[{"@id":"https://www.linkedin.com/in/benpeachey/"}],"http://xmlns.com/foaf/0.1/workplaceHomepage":[{"@id":"https://pdsinterop.org/"}]},{"@id":"/settings/preferencesFile.ttl"},{"@id":"/settings/privateTypeIndex.ttl"},{"@id":"/settings/publicTypeIndex.ttl"},{"@id":"_:b0","@type":["http://xmlns.com/foaf/0.1/Person"],"http://xmlns.com/foaf/0.1/name":[{"@value":"Alice"}],"http://xmlns.com/foaf/0.1/mbox_sha1sum":[{"@value":"9e9c84204ba63aa49a664273c5563c0cd78cc9ea"}],"http://www.w3.org/2000/01/rdf-schema#seeAlso":[{"@id":"http://example.org/alice"}]},{"@id":"_:b1","@type":["http://xmlns.com/foaf/0.1/Person"],"http://xmlns.com/foaf/0.1/name":[{"@value":"Bob"}],"http://xmlns.com/foaf/0.1/mbox_sha1sum":[{"@value":"1a9daad476f0158b81bc66b7b27b438b4b4c19c0"}],"http://www.w3.org/2000/01/rdf-schema#seeAlso":[{"@id":"http://bob.example.org/i"}]},{"@id":"http://bob.example.org/i"},{"@id":"http://example.org/alice"},{"@id":"http://www.ldodds.com/foaf/foaf-a-matic"},{"@id":"http://xmlns.com/foaf/0.1/Person"},{"@id":"http://xmlns.com/foaf/0.1/PersonalProfileDocument"},{"@id":"https://aki.artez.nl/"},{"@id":"https://pdsinterop.org/"},{"@id":"https://pother.ca"},{"@id":"https://www.gravatar.com/avatar/f8d7c4a4899736c59ec1e40c7021d477?s=1024"},{"@id":"https://www.linkedin.com/in/benpeachey/"},{"@id":"mailto:leigh@ldodds.com"},{"@id":"tel:0123456789"}]
json,
            ],
            'n-triples' => [
                Format::N_TRIPLES,
                <<<'ntriples'
</server#me> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .
</server#me> <http://www.w3.org/ns/ldp#inbox> </inbox/> .
</server#me> <http://www.w3.org/ns/solid/terms#account> </> .
</server#me> <http://www.w3.org/ns/solid/terms#privateTypeIndex> </settings/privateTypeIndex.ttl> .
</server#me> <http://www.w3.org/ns/solid/terms#publicTypeIndex> </settings/publicTypeIndex.ttl> .
</server#me> <http://www.w3.org/ns/pim/space#preferencesFile> </settings/preferencesFile.ttl> .
</server#me> <http://xmlns.com/foaf/0.1/depiction> <https://www.gravatar.com/avatar/f8d7c4a4899736c59ec1e40c7021d477?s=1024> .
</server#me> <http://xmlns.com/foaf/0.1/family_name> "Peachey" .
</server#me> <http://xmlns.com/foaf/0.1/givenname> "Ben" .
</server#me> <http://xmlns.com/foaf/0.1/homepage> <https://pother.ca> .
</server#me> <http://xmlns.com/foaf/0.1/knows> _:genid1 .
</server#me> <http://xmlns.com/foaf/0.1/knows> _:genid2 .
</server#me> <http://xmlns.com/foaf/0.1/mbox_sha1sum> "012e360c88b2bf940e6a52de3e5bbf59ccbdada6" .
</server#me> <http://xmlns.com/foaf/0.1/name> "Ben Peachey" .
</server#me> <http://xmlns.com/foaf/0.1/nick> "Potherca" .
</server#me> <http://xmlns.com/foaf/0.1/phone> <tel:0123456789> .
</server#me> <http://xmlns.com/foaf/0.1/schoolHomepage> <https://aki.artez.nl/> .
</server#me> <http://xmlns.com/foaf/0.1/title> "Mr." .
</server#me> <http://xmlns.com/foaf/0.1/workInfoHomepage> <https://www.linkedin.com/in/benpeachey/> .
</server#me> <http://xmlns.com/foaf/0.1/workplaceHomepage> <https://pdsinterop.org/> .
_:genid1 <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .
_:genid1 <http://xmlns.com/foaf/0.1/name> "Alice" .
_:genid1 <http://xmlns.com/foaf/0.1/mbox_sha1sum> "9e9c84204ba63aa49a664273c5563c0cd78cc9ea" .
_:genid1 <http://www.w3.org/2000/01/rdf-schema#seeAlso> <http://example.org/alice> .
_:genid2 <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Person> .
_:genid2 <http://xmlns.com/foaf/0.1/name> "Bob" .
_:genid2 <http://xmlns.com/foaf/0.1/mbox_sha1sum> "1a9daad476f0158b81bc66b7b27b438b4b4c19c0" .
_:genid2 <http://www.w3.org/2000/01/rdf-schema#seeAlso> <http://bob.example.org/i> .
</server> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/PersonalProfileDocument> .
</server> <http://xmlns.com/foaf/0.1/maker> </server#me> .
</server> <http://xmlns.com/foaf/0.1/primaryTopic> </server#me> .
</server> <http://webns.net/mvcb/generatorAgent> <http://www.ldodds.com/foaf/foaf-a-matic> .
</server> <http://webns.net/mvcb/errorReportsTo> <mailto:leigh@ldodds.com> .

ntriples,
            ],
            'n3' => [
                Format::NOTATION_3,
                <<<'n3'
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix ldp: <http://www.w3.org/ns/ldp#> .
@prefix ns0: <http://www.w3.org/ns/solid/terms#> .
@prefix ns1: <http://www.w3.org/ns/pim/space#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ns2: <http://webns.net/mvcb/> .

</server#me>
  a foaf:Person ;
  ldp:inbox </inbox/> ;
  ns0:account </> ;
  ns0:privateTypeIndex </settings/privateTypeIndex.ttl> ;
  ns0:publicTypeIndex </settings/publicTypeIndex.ttl> ;
  ns1:preferencesFile </settings/preferencesFile.ttl> ;
  foaf:depiction <https://www.gravatar.com/avatar/f8d7c4a4899736c59ec1e40c7021d477?s=1024> ;
  foaf:family_name "Peachey" ;
  foaf:givenname "Ben" ;
  foaf:homepage <https://pother.ca> ;
  foaf:knows [
    a foaf:Person ;
    foaf:name "Alice" ;
    foaf:mbox_sha1sum "9e9c84204ba63aa49a664273c5563c0cd78cc9ea" ;
    rdfs:seeAlso <http://example.org/alice>
  ], [
    a foaf:Person ;
    foaf:name "Bob" ;
    foaf:mbox_sha1sum "1a9daad476f0158b81bc66b7b27b438b4b4c19c0" ;
    rdfs:seeAlso <http://bob.example.org/i>
  ] ;
  foaf:mbox_sha1sum "012e360c88b2bf940e6a52de3e5bbf59ccbdada6" ;
  foaf:name "Ben Peachey" ;
  foaf:nick "Potherca" ;
  foaf:phone <tel:0123456789> ;
  foaf:schoolHomepage <https://aki.artez.nl/> ;
  foaf:title "Mr." ;
  foaf:workInfoHomepage <https://www.linkedin.com/in/benpeachey/> ;
  foaf:workplaceHomepage <https://pdsinterop.org/> .

</server>
  a foaf:PersonalProfileDocument ;
  foaf:maker </server#me> ;
  foaf:primaryTopic </server#me> ;
  ns2:generatorAgent <http://www.ldodds.com/foaf/foaf-a-matic> ;
  ns2:errorReportsTo <mailto:leigh@ldodds.com> .


n3,
            ],
            'rdf xml' => [Format::RDF_XML, file_get_contents(__DIR__ . '/../fixtures/foaf.rdf')],
            'turtle' => [
                Format::TURTLE,
                <<<'TURTLE'
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix ldp: <http://www.w3.org/ns/ldp#> .
@prefix ns0: <http://www.w3.org/ns/solid/terms#> .
@prefix ns1: <http://www.w3.org/ns/pim/space#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ns2: <http://webns.net/mvcb/> .

</server#me>
  a foaf:Person ;
  ldp:inbox </inbox/> ;
  ns0:account </> ;
  ns0:privateTypeIndex </settings/privateTypeIndex.ttl> ;
  ns0:publicTypeIndex </settings/publicTypeIndex.ttl> ;
  ns1:preferencesFile </settings/preferencesFile.ttl> ;
  foaf:depiction <https://www.gravatar.com/avatar/f8d7c4a4899736c59ec1e40c7021d477?s=1024> ;
  foaf:family_name "Peachey" ;
  foaf:givenname "Ben" ;
  foaf:homepage <https://pother.ca> ;
  foaf:knows [
    a foaf:Person ;
    foaf:name "Alice" ;
    foaf:mbox_sha1sum "9e9c84204ba63aa49a664273c5563c0cd78cc9ea" ;
    rdfs:seeAlso <http://example.org/alice>
  ], [
    a foaf:Person ;
    foaf:name "Bob" ;
    foaf:mbox_sha1sum "1a9daad476f0158b81bc66b7b27b438b4b4c19c0" ;
    rdfs:seeAlso <http://bob.example.org/i>
  ] ;
  foaf:mbox_sha1sum "012e360c88b2bf940e6a52de3e5bbf59ccbdada6" ;
  foaf:name "Ben Peachey" ;
  foaf:nick "Potherca" ;
  foaf:phone <tel:0123456789> ;
  foaf:schoolHomepage <https://aki.artez.nl/> ;
  foaf:title "Mr." ;
  foaf:workInfoHomepage <https://www.linkedin.com/in/benpeachey/> ;
  foaf:workplaceHomepage <https://pdsinterop.org/> .

</server>
  a foaf:PersonalProfileDocument ;
  foaf:maker </server#me> ;
  foaf:primaryTopic </server#me> ;
  ns2:generatorAgent <http://www.ldodds.com/foaf/foaf-a-matic> ;
  ns2:errorReportsTo <mailto:leigh@ldodds.com> .


TURTLE,
            ],
        ];
    }
}
