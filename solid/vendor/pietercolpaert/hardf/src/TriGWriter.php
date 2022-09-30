<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/** a clone of the N3Writer class from the N3js code by Ruben Verborgh **/
/** TriGWriter writes both Turtle and TriG from our triple representation depending on the options */
class TriGWriter
{
    /**
     * Matches a literal as represented in memory
     *
     * @var string
     */
    const LITERALMATCHER = '/^"(.*)"(?:\\^\\^(.+)|@([\\-a-z]+))?$/is';

    /**
     * rdf:type predicate (for 'a' abbreviation)
     *
     * @var string
     */
    const RDF_PREFIX = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    /**
     * @var string
     */
    const RDF_TYPE = self::RDF_PREFIX.'type';

    /**
     * Characters in literals that require escaping
     *
     * @var string
     */
    const ESCAPE = '/["\\\\\\t\\n\\r\\b\\f]/';

    /**
     * @var array
     */
    private $escapeReplacements;

    /**
     * matches a prefixed name or IRI that begins with one of the added prefixes
     *
     * @var string
     */
    private $prefixRegex = '/$0^/';

    /**
     * @var string|null
     */
    private $subject;

    /**
     * @var string|null
     */
    private $graph;

    /**
     * @var array
     */
    private $prefixIRIs;

    /**
     * @var bool
     */
    private $blocked = false;

    private $predicate;

    /**
     * @var string|null
     */
    private $string;

    /**
     * @var callable
     */
    private $readCallback;

    /**
     * Replaces a character by its escaped version
     *
     * @todo remove
     */
    private $characterReplacer;

    /**
     * @var callable
     */
    private $writeTriple;

    /**
     * @var callable
     */
    private $writeTripleLine;

    public function __construct($options = [], $readCallback = null)
    {
        $this->escapeReplacements = [
            '\\' => '\\\\', '"' => '\\"', "\t" => '\\t',
            "\n" => '\\n', "\r" => '\\r', \chr(8) => '\\b', "\f" => '\\f',
        ];

        $this->setReadCallback($readCallback);
        $this->initWriter();

        /* Initialize writer, depending on the format*/
        $this->subject = null;
        if (!isset($options['format']) || !(preg_match('/triple|quad/i', $options['format']))) {
            $this->graph = '';
            $this->prefixIRIs = [];
            if (isset($options['prefixes'])) {
                $this->addPrefixes($options['prefixes']);
            }
        } else {
            $this->writeTriple = $this->writeTripleLine;
        }

        /*
         * @todo make that a separate function
         *
         * @param mixed $character
         */
        $this->characterReplacer = function ($character): string {
            // Replace a single character by its escaped version
            $character = $character[0];
            if (\strlen($character) > 0 && isset($this->escapeReplacements[$character[0]])) {
                return $this->escapeReplacements[$character[0]];
            } else {
                return $character; //no escaping necessary, should not happen, or something is wrong in our regex
            }
        };
    }

    public function setReadCallback($readCallback)
    {
        $this->readCallback = $readCallback;
    }

    private function initWriter()
    {
        // ### `_writeTriple` writes the triple to the output stream
        $this->writeTriple = function ($subject, $predicate, $object, $graph) {
            if (empty($graph)) {
                $graph = null;
            }

            // Write the graph's label if it has changed
            if ($this->graph !== $graph) {
                // Close the previous graph and start the new one
                $lineToWrite = null === $this->subject ? '' : ($this->graph ? "\n}\n" : '.'.PHP_EOL);
                $lineToWrite .= isset($graph) ? $this->encodeIriOrBlankNode($graph).' {'.PHP_EOL : '';
                $this->write($lineToWrite);

                $this->subject = null;

                // Don't treat identical blank nodes as repeating graphs
                if (null === $graph) {
                    $this->graph = $graph;
                } else {
                    $this->graph = '[' !== $graph[0] ? $graph : ']';
                }
            }

            // Don't repeat the subject if it's the same
            if ($this->subject === $subject) {
                // Don't repeat the predicate if it's the same
                if ($this->predicate === $predicate) {
                    $this->write(', '.$this->encodeObject($object));
                }
                // Same subject, different predicate
                else {
                    $this->predicate = $predicate;
                    $this->write(";\n    ".$this->encodePredicate($predicate).' '.$this->encodeObject($object));
                }
            }
            // Different subject; write the whole triple
            else {
                $lineToWrite = (null === $this->subject ? '' : ".\n");

                $this->subject = $subject;
                $lineToWrite .= $this->encodeSubject($subject);

                $this->predicate = $predicate;
                $lineToWrite .= ' '.$this->encodePredicate($predicate);
                $lineToWrite .= ' '.$this->encodeObject($object);

                $this->write($lineToWrite);
            }
        };

        // ### `_writeTripleLine` writes the triple or quad to the output stream as a single line
        $this->writeTripleLine = function ($subject, $predicate, $object, $graph) {
            if (isset($graph) && '' === $graph) {
                $graph = null;
            }
            // Don't use prefixes
            unset($this->prefixMatch);

            // Write the triple
            $tripleToWrite = $this->encodeIriOrBlankNode($subject);
            $tripleToWrite .= ' '.$this->encodeIriOrBlankNode($predicate);
            $tripleToWrite .= ' '.$this->encodeObject($object);
            $tripleToWrite .= (isset($graph) ? ' '.$this->encodeIriOrBlankNode($graph).'.'.PHP_EOL : '.'.PHP_EOL);

            $this->write($tripleToWrite);
        };
    }

    /**
     * writes the argument to the output stream
     */
    private function write(string $string)
    {
        if ($this->blocked) {
            throw new \Exception('Cannot write because the writer has been closed.');
        } else {
            if (isset($this->readCallback)) {
                \call_user_func($this->readCallback, $string);
            } else {
                //buffer all
                $this->string .= $string;
            }
        }
    }

    // ### Reads a bit of the string
    public function read(): string
    {
        $string = $this->string;
        $this->string = '';

        return $string;
    }

    // ### `_encodeIriOrBlankNode` represents an IRI or blank node
    private function encodeIriOrBlankNode($entity)
    {
        // A blank node or list is represented as-is
        $firstChar = substr($entity, 0, 1);
        if ('[' === $firstChar || '(' === $firstChar || '_' === $firstChar && ':' === substr($entity, 1, 1)) {
            return $entity;
        }
        // Escape special characters
        if (preg_match(self::ESCAPE, $entity)) {
            $entity = preg_replace_callback(self::ESCAPE, $this->characterReplacer, $entity);
        }

        // Try to represent the IRI as prefixed name
        preg_match($this->prefixRegex, $entity, $prefixMatch);
        if (!isset($prefixMatch[1]) && !isset($prefixMatch[2])) {
            if (preg_match('/(.*?:)/', $entity, $match) && isset($this->prefixIRIs) && \in_array($match[1], $this->prefixIRIs)) {
                return $entity;
            } else {
                return '<'.$entity.'>';
            }
        } else {
            return !isset($prefixMatch[1]) ? $entity : $this->prefixIRIs[$prefixMatch[1]].$prefixMatch[2];
        }
    }

    // ### `_encodeLiteral` represents a literal
    private function encodeLiteral($value, $type = null, $language = null)
    {
        // Escape special characters
        if (preg_match(self::ESCAPE, $value)) {
            $value = preg_replace_callback(self::ESCAPE, $this->characterReplacer, $value);
        }
        $value = $value;
        // Write the literal, possibly with type or language
        if (isset($language)) {
            return '"'.$value.'"@'.$language;
        } elseif (isset($type)) {
            return '"'.$value.'"^^'.$this->encodeIriOrBlankNode($type);
        } else {
            return '"'.$value.'"';
        }
    }

    // ### `_encodeSubject` represents a subject
    private function encodeSubject(string $subject)
    {
        if ('"' === $subject[0]) {
            throw new \Exception('A literal as subject is not allowed: '.$subject);
        }

        // Don't treat identical blank nodes as repeating subjects
        if ('[' === $subject[0]) {
            $this->subject = ']';
        }

        return $this->encodeIriOrBlankNode($subject);
    }

    // ### `_encodePredicate` represents a predicate
    private function encodePredicate(string $predicate)
    {
        if ('"' === $predicate[0]) {
            throw new \Exception('A literal as predicate is not allowed: '.$predicate);
        }

        return self::RDF_TYPE === $predicate ? 'a' : $this->encodeIriOrBlankNode($predicate);
    }

    /**
     * represents an object
     *
     * @param array<int, string|int>|string $object
     */
    private function encodeObject($object)
    {
        // Represent an IRI or blank node
        if ('"' !== $object[0]) {
            return $this->encodeIriOrBlankNode($object);
        }
        // Represent a literal
        if (preg_match(self::LITERALMATCHER, $object, $matches)) {
            return $this->encodeLiteral($matches[1], isset($matches[2]) ? $matches[2] : null, isset($matches[3]) ? $matches[3] : null);
        } else {
            throw new \Exception('Invalid literal: '.$object);
        }
    }

    /**
     * adds the triple to the output stream
     *
     * @param string|array<string, string|null> $subject
     * @param string                            $predicate
     * @param string|array<string, string|null> $object
     * @param string|null                       $graph
     */
    public function addTriple($subject, $predicate = null, $object = null, $graph = null): void
    {
        /*
         * The triple was given as a triple object, so shift parameters
         *
         * TODO deprecate that and remove this in next major version. That is bad style, instead adapt
         *      callers to split S, P, O, G as different paramaters. This change also allows better
         *      static code analysis
         */
        if (\is_array($subject)) {
            $g = isset($subject['graph']) ? $subject['graph'] : null;
            \call_user_func($this->writeTriple, $subject['subject'], $subject['predicate'], $subject['object'], $g, $predicate);
        }

        // The optional `graph` parameter was not provided
        elseif (!\is_string($graph)) {
            \call_user_func($this->writeTriple, $subject, $predicate, $object, '', $graph);
        }
        // The `graph` parameter was provided
        else {
            \call_user_func($this->writeTriple, $subject, $predicate, $object, $graph);
        }
    }

    /**
     * adds the triples to the output stream
     *
     * @param array<int, array<string, string>> $triples
     */
    public function addTriples(array $triples): void
    {
        for ($i = 0; $i < \count($triples); ++$i) {
            $this->addTriple($triples[$i]);
        }
    }

    /**
     * adds the prefix to the output stream
     */
    public function addPrefix(string $prefix, string $iri): void
    {
        $prefixes = [];
        $prefixes[$prefix] = $iri;
        $this->addPrefixes($prefixes);
    }

    /**
     * adds the prefixes to the output stream
     *
     * @param array<string, string> $prefixes
     */
    public function addPrefixes(array $prefixes): void
    {
        // Add all useful prefixes
        $hasPrefixes = false;
        foreach ($prefixes as $prefix => $iri) {
            // Verify whether the prefix can be used and does not exist yet
            $check = !isset($this->prefixIRIs[$iri]) || $this->prefixIRIs[$iri] !== ($prefix.':');
            if (preg_match('/[#\/]$/', $iri) && $check) {
                $hasPrefixes = true;
                $this->prefixIRIs[$iri] = $prefix.':';
                // Finish a possible pending triple
                if (null !== $this->subject) {
                    $this->write($this->graph ? "\n}\n" : ".\n");
                    $this->subject = null;
                    $this->graph = '';
                }
                // Write prefix
                $this->write('@prefix '.$prefix.': <'.$iri.">.\n");
            }
        }
        // Recreate the prefix matcher
        if ($hasPrefixes) {
            $IRIlist = '';
            $prefixList = '';
            foreach ($this->prefixIRIs as $prefixIRI => $iri) {
                $IRIlist .= $IRIlist ? '|'.$prefixIRI : $prefixIRI;
                $prefixList .= ($prefixList ? '|' : '').$iri;
            }
            $IRIlist = preg_replace("/([\]\/\(\)\*\+\?\.\\\$])/", '${1}', $IRIlist);
            $this->prefixRegex = '%^(?:'.$prefixList.')[^/]*$|'.'^('.$IRIlist.')([a-zA-Z][\\-_a-zA-Z0-9]*)$%';
        }
        // End a prefix block with a newline
        $this->write($hasPrefixes ? "\n" : '');
    }

    /**
     * creates a blank node with the given content
     *
     * @param string|array<string, string>|null $object
     */
    public function blank($predicate = null, $object = null): string
    {
        $children = $predicate;
        $child = '';
        $length = '';
        // Empty blank node
        if (!isset($predicate)) {
            $children = [];
        }
        // Blank node passed as blank("$predicate", "object")
        elseif (\is_string($predicate)) {
            $children = [['predicate' => $predicate, 'object' => $object]];
        }
        // Blank node passed as blank({ predicate: $predicate, object: $object })
        elseif (\is_array($predicate) && isset($predicate['predicate'])) {
            $children = [$predicate];
        }

        switch ($length = \count($children)) {
            case 0:
                // Generate an empty blank node
                return '[]';
            case 1:
                // Generate a non-nested one-triple blank node
                $child = $children[0];
                if ('[' !== $child['object'][0]) {
                    return '[ '.$this->encodePredicate($child['predicate']).' '.
                        $this->encodeObject($child['object']).' ]';
                }
                        // no break
            default:
                // Generate a multi-triple or nested blank node
                $contents = '[';
                // Write all triples in order
                for ($i = 0; $i < $length; ++$i) {
                    $child = $children[$i];
                    // Write only the object is the $predicate is the same as the previous
                    if ($child['predicate'] === $predicate) {
                        $contents .= ', '.$this->encodeObject($child['object']);
                    }
                    // Otherwise, write the $predicate and the object
                    else {
                        $contents .= ($i ? ";\n  " : "\n  ").
                            $this->encodePredicate($child['predicate']).' '.
                            $this->encodeObject($child['object']);
                        $predicate = $child['predicate'];
                    }
                }

                return $contents."\n]";
        }
    }

    /**
     * creates a list node with the given content
     *
     * @param array<int, string> $elements
     */
    public function addList(array $elements = []): string
    {
        $length = \count($elements);
        $contents = [];
        for ($i = 0; $i < $length; ++$i) {
            $contents[$i] = $this->encodeObject($elements[$i]);
        }

        return '('.implode(' ', $contents).')';
    }

    /**
     * Signals the end of the output stream
     */
    public function end(): ?string
    {
        // Finish a possible pending triple
        if (null !== $this->subject) {
            $this->write($this->graph ? "\n}\n" : ".\n");
            $this->subject = null;
        }
        if (isset($this->readCallbacks)) {
            \call_user_func($this->readCallback, $this->string);
        }

        // Disallow further writing
        $this->blocked = true;
        if (!isset($this->readCallback)) {
            return $this->string;
        }

        return null;
    }
}
