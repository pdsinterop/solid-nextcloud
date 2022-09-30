<?php
    // performance test
    require_once( __DIR__.'/../../src/path.php' );
    require_once( __DIR__.'/../../src/path/Value.php');

    // 100.000 times the same path - 1.9s naive implementation - 0.12s with cache
    $inputPath = 'a/../path//going/../somewhere';
    $max = 100000;
    $starttime = microtime(true);
    for ($i =0; $i<$max; $i++) {
        $outputPath = \arc\path::collapse( $inputPath );
    }
    $endtime = microtime(true);
    echo $outputPath."<br>\n";
    echo $endtime - $starttime."<br>\n";
    echo "<br>\n";

    // 100 times a 1000 random paths - 1.5 - 1.9 naive implementation - 0.14 with cache
    $max2 = 1000;
    $paths = array();
    $filenames = array( 'a', 'some', '..', 'filenames', '..', 'pick', '.', 'from', '' );
    for ($i=0; $i<$max2; $i++) {
        $path = '';
        if ($i & 1) {
            $path .= '/';
        }
        for ($ii=0; $ii<5; $ii++) {
            $path .=  $filenames[ array_rand( $filenames, 1 ) ] .'/';
        }
        $paths[] = $path;
    }

    $starttime = microtime(true);
    for ($ii=0; $ii<100; $ii++) {
        for ($i =0; $i<$max2; $i++) {
            $outputPath = \arc\path::collapse( $paths[$i] );
        }
    }
    $endtime = microtime(true);
    echo $outputPath;
    echo $endtime - $starttime;
