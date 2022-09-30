<?php

include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParser;

if (2 !== count($argv)) {
    echo 'Usage: parser-perf.php filename';
    exit;
}

$filename = $argv[1];
$base = 'file://'.$filename;

$TEST = microtime(true);

$count = 0;
$parser = new TriGParser(['documentIRI' => $base], function ($error, $triple) use (&$count, $TEST, $filename) {
    if ($triple) {
        ++$count;
    } else {
        echo '- Parsing file '.$filename.': '.(microtime(true) - $TEST)."s\n";
        echo '* Triples parsed: '.$count."\n";
        echo '* Memory usage: '.(memory_get_usage() / 1024 / 1024)."MB\n";
    }
});

$handle = fopen($filename, 'r');
if ($handle) {
    while (false !== ($line = fgets($handle, 4096))) {
        $parser->parseChunk($line);
    }
    $parser->end();
    fclose($handle);
} else {
    // error opening the file.
    echo 'File not found '.$filename;
}
