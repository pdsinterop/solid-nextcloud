<?php

include_once(__DIR__ . '/../vendor/autoload.php');
use pietercolpaert\hardf\TriGWriter;

//Add prefixes in the constructor
$writer = new TriGWriter([
    "prefixes" => [
        "schema" =>"http://schema.org/",
        "dct" =>"http://purl.org/dc/terms/",
        "geo" =>"http://www.w3.org/2003/01/geo/wgs84_pos#",
        "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
        "rdfs"=> "http://www.w3.org/2000/01/rdf-schema#"
        ]
]);

$writer->addPrefix("ex","http://example.org/");
$writer->addTriple("schema:Person","dct:title","\"Person\"@en","http://example.org/#test");
$writer->addTriple("schema:Person","schema:label","\"Person\"@en","http://example.org/#test");
$writer->addTriple("ex:1","dct:title","\"Person1\"@en","http://example.org/#test");
$writer->addTriple("ex:1","http://www.w3.org/1999/02/22-rdf-syntax-ns#type","schema:Person","http://example.org/#test");
$writer->addTriple("ex:2","dct:title","\"Person2\"@en","http://example.org/#test");
$writer->addTriple("schema:Person","dct:title","\"Person\"@en","http://example.org/#test2");
echo $writer->end();
