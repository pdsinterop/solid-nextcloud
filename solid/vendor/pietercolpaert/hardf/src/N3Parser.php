<?php

declare(strict_types=1);

/**An alias for TriGParser, with the default format set to N3 */

namespace pietercolpaert\hardf;

/** a clone of the N3Parser class from the N3js code by Ruben Verborgh **/
/** N3Parser parses Turtle, TriG, N-Quads, N-Triples and N3 to our triple representation (see README.md) */
class N3Parser extends TriGParser
{
    public function __construct($options)
    {
        if (!isset($options['format'])) {
            $options['format'] = 'n3';
        }
        parent::__construct($options);
    }
}
