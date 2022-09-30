#!/usr/bin/php
<?php
/** Validates TriG, Turtle, N3, N-QUADS or N-TRIPLES input */
include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParser;

$format = 'trig';
if (isset($argv[1])) {
    $format = $argv[1];
}
$parser = new TriGParser(['format' => $format]);
$errored = false;
$finished = false;
$tripleCount = 0;
$line = true;
while (!$finished && $line) {
    try {
        $line = fgets(STDIN);
        if ($line) {
            $tripleCount += count($parser->parseChunk($line));
        } else {
            $tripleCount += count($parser->end());
            $finished = true;
        }
    } catch (\Exception $e) {
        echo $e->getMessage()."\n";
        $errored = true;
    }
}
if (!$errored) {
    echo 'Parsed '.$tripleCount." triples successfully.\n";
}
