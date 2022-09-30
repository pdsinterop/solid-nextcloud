<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/**
 * a clone of the N3Parser class from the N3js code by Ruben Verborgh
 *
 * TriGParser parses Turtle, TriG, N-Quads, N-Triples and N3 to our triple representation (see README.md)
 */
class TriGParser
{
    const RDF_PREFIX = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    const RDF_NIL = self::RDF_PREFIX.'nil';
    const RDF_FIRST = self::RDF_PREFIX.'first';
    const RDF_REST = self::RDF_PREFIX.'rest';
    const QUANTIFIERS_GRAPH = 'urn:n3:quantifiers';

    private $absoluteIRI = '/^[a-z][a-z0-9+.-]*:/i';
    private $schemeAuthority = '/^(?:([a-z][a-z0-9+.-]*:))?(?:\\/\\/[^\\/]*)?/i';
    private $dotSegments = '/(?:^|\\/)\\.\\.?(?:$|[\\/#?])/';

    // The next ID for new blank nodes
    private $blankNodePrefix;
    private $blankNodeCount = 0;

    private $contextStack;
    private $graph;

    private $afterPath;
    private $base;
    private $basePath;
    private $baseRoot;
    private $baseScheme;
    private $callback;
    private $completeLiteral;
    private $error;
    private $explicitQuantifiers;
    private $getContextEndReader;
    private $getPathReader;
    private $inversePredicate;
    private $lexer;
    private $n3Mode;
    private $object;
    private $predicate;
    private $prefix;
    private $prefixes;
    private $prefixCallback;
    private $quantified;
    private $quantifiedPrefix;
    private $readBackwardPath;
    private $readBaseIRI;
    private $readBlankNodeHead;
    private $readBlankNodePunctuation;
    private $readBlankNodeTail;
    private $readDataTypeOrLang;
    private $readDeclarationPunctuation;
    private $readEntity;
    private $readFormulaTail;
    private $readForwardPath;
    private $readGraph;
    private $readListItem;
    private $readListItemDataTypeOrLang;
    private $readNamedGraphLabel;
    private $readNamedGraphBlankLabel;
    private $readObject;
    private $readPath;
    private $readPredicate;
    private $readPredicateAfterBlank;
    private $readPredicateOrNamedGraph;
    private $readPrefix;
    private $readPrefixIRI;
    private $readPunctuation;
    private $readQuadPunctuation;
    private $readQuantifierList;
    private $readQuantifierPunctuation;
    private $readSubject;
    private $removeDotSegments;
    private $resolveIRI;
    private $sparqlStyle;
    private $subject;
    private $supportsNamedGraphs;
    private $supportsQuads;
    private $triple;
    private $tripleCallback;

    private $readInTopContext;
    private $readCallback;

    // Constructor
    public function __construct($options = [], $tripleCallback = null, $prefixCallback = null)
    {
        $this->setTripleCallback($tripleCallback);
        $this->setPrefixCallback($prefixCallback);
        $this->contextStack = [];
        $this->graph = null;

        //This will initiate the callback methods
        $this->initReaders();

        // Set the document IRI
        $this->setBase(isset($options['documentIRI']) ? $options['documentIRI'] : null);

        // Set supported features depending on the format
        if (!isset($options['format'])) {
            $options['format'] = '';
        }
        $format = (string) $options['format'];
        $format = strtolower($format);
        $isTurtle = 'turtle' === $format;
        $isTriG = 'trig' === $format;

        $isNTriples = false !== strpos($format, 'triple') ? true : false;
        $isNQuads = false !== strpos($format, 'quad') ? true : false;
        $isN3 = false !== strpos($format, 'n3') ? true : false;
        $this->n3Mode = $isN3;
        $isLineMode = $isNTriples || $isNQuads;
        if (!($this->supportsNamedGraphs = !($isTurtle || $isN3))) {
            $this->readPredicateOrNamedGraph = $this->readPredicate;
        }
        $this->supportsQuads = !($isTurtle || $isTriG || $isNTriples || $isN3);
        // Disable relative IRIs in N-Triples or N-Quads mode
        if ($isLineMode) {
            $this->base = '';
            $this->resolveIRI = function ($token) {
                \call_user_func($this->error, 'Disallowed relative IRI', $token);

                $this->subject = null;

                return $this->callback = function () {};
            };
        }
        $this->blankNodePrefix = null;
        if (isset($options['blankNodePrefix'])) {
            $this->blankNodePrefix = '_:'.preg_replace('/^_:/', '', $options['blankNodePrefix']);
        }

        $this->lexer = isset($options['lexer']) ? $options['lexer'] : new N3Lexer(['lineMode' => $isLineMode, 'n3' => $isN3]);
        // Disable explicit quantifiers by default
        $this->explicitQuantifiers = isset($options['explicitQuantifiers']) ? $options['explicitQuantifiers'] : null;

        // The read callback is the next function to be executed when a token arrives.
        // We start reading in the top context.
        $this->readCallback = $this->readInTopContext;
        $this->sparqlStyle = false;
        $this->prefixes = [];
        $this->prefixes['_'] = isset($this->blankNodePrefix) ? $this->blankNodePrefix : '_:b'.$this->blankNodeCount.'_';
        $this->inversePredicate = false;
        $this->quantified = [];
    }

    // ## Private class methods
    // ### `_resetBlankNodeIds` restarts blank node identification
    public function _resetBlankNodeIds()
    {
        $this->blankNodeCount = 0;
    }

    // ### `_setBase` sets the base IRI to resolve relative IRIs
    private function setBase($baseIRI = null)
    {
        if (!$baseIRI) {
            $this->base = null;
        } else {
            // Remove fragment if present
            $fragmentPos = strpos($baseIRI, '#');
            if (false !== $fragmentPos) {
                $baseIRI = substr($baseIRI, 0, $fragmentPos);
            }
            // Set base IRI and its components
            $this->base = $baseIRI;
            $this->basePath = false === strpos($baseIRI, '/') ? $baseIRI : preg_replace('/[^\/?]*(?:\?.*)?$/', '', $baseIRI);
            preg_match($this->schemeAuthority, $baseIRI, $matches);
            $this->baseRoot = isset($matches[0]) ? $matches[0] : '';
            $this->baseScheme = isset($matches[1]) ? $matches[1] : '';
        }
    }

    // ### `_saveContext` stores the current parsing context
    // when entering a new scope (list, blank node, formula)
    private function saveContext($type, $graph, $subject, $predicate, $object)
    {
        $n3Mode = $this->n3Mode ?: null;
        array_push($this->contextStack, [
            'subject' => $subject, 'predicate' => $predicate, 'object' => $object,
            'graph' => $graph, 'type' => $type,
            'inverse' => $n3Mode ? $this->inversePredicate : false,
            'blankPrefix' => $n3Mode ? $this->prefixes['_'] : '',
            'quantified' => $n3Mode ? $this->quantified : null,
        ]);
        // The settings below only apply to N3 streams
        if ($n3Mode) {
            // Every new scope resets the predicate direction
            $this->inversePredicate = false;
            // In N3, blank nodes are scoped to a formula
            // (using a dot as separator, as a blank node label cannot start with it)
            $this->prefixes['_'] = $this->graph.'.';
            // Quantifiers are scoped to a formula TODO: is this correct?
            $this->quantified = $this->quantified;
        }
    }

    // ### `_restoreContext` restores the parent context
    // when leaving a scope (list, blank node, formula)
    private function restoreContext()
    {
        $context = array_pop($this->contextStack);
        $n3Mode = $this->n3Mode;
        $this->subject = $context['subject'];
        $this->predicate = $context['predicate'];
        $this->object = $context['object'];
        $this->graph = $context['graph'];
        // The settings below only apply to N3 streams
        if ($n3Mode) {
            $this->inversePredicate = $context['inverse'];
            $this->prefixes['_'] = $context['blankPrefix'];
            $this->quantified = $context['quantified'];
        }
    }

    private function initReaders()
    {
        // ### `_readInTopContext` reads a token when in the top context
        $this->readInTopContext = function ($token) {
            if (!isset($token['type'])) {
                $token['type'] = '';
            }
            switch ($token['type']) {
                // If an EOF token arrives in the top context, signal that we're done
                case 'eof':
                if (null !== $this->graph) {
                    return \call_user_func($this->error, 'Unclosed graph', $token);
                }
                unset($this->prefixes['_']);
                if ($this->callback) {
                    return \call_user_func($this->callback, null, null, $this->prefixes);
                }
                // It could be a prefix declaration
                // no break
                case 'PREFIX':
                $this->sparqlStyle = true;
                // no break
                case '@prefix':
                return $this->readPrefix;
                // It could be a base declaration
                case 'BASE':
                $this->sparqlStyle = true;
                // no break
                case '@base':
                return $this->readBaseIRI;
                // It could be a graph
                case '{':
                if ($this->supportsNamedGraphs) {
                    $this->graph = '';
                    $this->subject = null;

                    return $this->readSubject;
                }
                // no break
                case 'GRAPH':
                if ($this->supportsNamedGraphs) {
                    return $this->readNamedGraphLabel;
                }
                // Otherwise, the next token must be a subject
                // no break
                default:
                return \call_user_func($this->readSubject, $token);
            }
        };

        /*
         * reads an IRI, prefixed name, blank node, or variable
         *
         * @return null|string|object
         */
        $this->readEntity = function ($token, $quantifier = null) {
            $value = null;
            switch ($token['type']) {
                // Read a relative or absolute IRI
                case 'IRI':
                case 'typeIRI':
                    if (null === $this->base || preg_match($this->absoluteIRI, $token['value'])) {
                        $value = $token['value'];
                    } else {
                        $value = \call_user_func($this->resolveIRI, $token);
                    }
                    break;
                    // Read a blank node or prefixed name
                case 'type':
                case 'blank':
                case 'prefixed':
                    if (!isset($this->prefixes[$token['prefix']])) {
                        return \call_user_func($this->error, 'Undefined prefix "'.$token['prefix'].':"', $token);
                    }

                    $prefix = $this->prefixes[$token['prefix']];
                    $value = $prefix.$token['value'];
                    break;
                    // Read a variable
                case 'var':
                    return $token['value'];
                    // Everything else is not an entity
                default:
                    return \call_user_func($this->error, 'Expected entity but got '.$token['type'], $token);
            }
            // In N3 mode, replace the entity if it is quantified
            if (!isset($quantifier) && $this->n3Mode && isset($this->quantified[$value])) {
                $value = $this->quantified[$value];
            }

            return $value;
        };

        // ### `_readSubject` reads a triple's subject
        $this->readSubject = function ($token) {
            $this->predicate = null;
            switch ($token['type']) {
                case '[':
                    // Start a new triple with a new blank node as subject
                    $this->saveContext('blank', $this->graph, $this->subject = '_:b'.$this->blankNodeCount++, null, null);

                    return $this->readBlankNodeHead;
                case '(':;
                    // Start a new list
                    $this->saveContext('list', $this->graph, self::RDF_NIL, null, null);
                    $this->subject = null;

                    return $this->readListItem;
                case '{':
                    // Start a new formula
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Unexpected graph', $token);
                    }
                    $this->saveContext('formula', $this->graph, $this->graph = '_:b'.$this->blankNodeCount++, null, null);

                    return $this->readSubject;
                case '}':
                    // No subject; the graph in which we are reading is closed instead
                    return \call_user_func($this->readPunctuation, $token);
                case '@forSome':
                    $this->subject = null;
                    $this->predicate = 'http://www.w3.org/2000/10/swap/reify#forSome';
                    $this->quantifiedPrefix = '_:b';

                    return $this->readQuantifierList;
                case '@forAll':
                    $this->subject = null;
                    $this->predicate = 'http://www.w3.org/2000/10/swap/reify#forAll';
                    $this->quantifiedPrefix = '?b-';

                    return $this->readQuantifierList;
                default:
                    // Read the subject entity
                    $this->subject = \call_user_func($this->readEntity, $token);
                    if (null == $this->subject) {
                        return;
                    }
                    // In N3 mode, the subject might be a path
                    if ($this->n3Mode) {
                        return \call_user_func($this->getPathReader, $this->readPredicateOrNamedGraph);
                    }
            }

            // The next token must be a predicate,
            // or, if the subject was actually a graph IRI, a named graph
            return $this->readPredicateOrNamedGraph;
        };

        // ### `_readPredicate` reads a triple's predicate
        $this->readPredicate = function ($token) {
            $type = $token['type'];
            switch ($type) {
                case 'inverse':
                    $this->inversePredicate = true;
                    // no break
                case 'abbreviation':
                    $this->predicate = $token['value'];
                    break;
                case '.':
                case ']':
                case '}':
                    // Expected predicate didn't come, must have been trailing semicolon
                    if (null === $this->predicate) {
                        return \call_user_func($this->error, 'Unexpected '.$type, $token);
                    }
                    $this->subject = null;

                    return ']' === $type ? \call_user_func($this->readBlankNodeTail, $token) : \call_user_func($this->readPunctuation, $token);
                case ';':
                    // Extra semicolons can be safely ignored
                    return $this->readPredicate;
                case 'blank':
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Disallowed blank node as predicate', $token);
                    }
                        // no break
                default:
                    $this->predicate = \call_user_func($this->readEntity, $token);
                    if (null == $this->predicate) {
                        return;
                    }
            }
            // The next token must be an object
            return $this->readObject;
        };

        // ### `_readObject` reads a triple's object
        $this->readObject = function ($token) {
            switch ($token['type']) {
                case 'literal':
                $this->object = $token['value'];

                return $this->readDataTypeOrLang;
                case '[':
                // Start a new triple with a new blank node as subject
                $this->saveContext('blank', $this->graph, $this->subject, $this->predicate,
                $this->subject = '_:b'.$this->blankNodeCount++);

                return $this->readBlankNodeHead;
                case '(':
                // Start a new list
                $this->saveContext('list', $this->graph, $this->subject, $this->predicate, self::RDF_NIL);
                $this->subject = null;

                return $this->readListItem;
                case '{':
                // Start a new formula
                if (!$this->n3Mode) {
                    return \call_user_func($this->error, 'Unexpected graph', $token);
                }
                $this->saveContext('formula', $this->graph, $this->subject, $this->predicate,
                $this->graph = '_:b'.$this->blankNodeCount++);

                return $this->readSubject;
                default:
                // Read the object entity
                $this->object = \call_user_func($this->readEntity, $token);
                if (null == $this->object) {
                    return;
                }
                // In N3 mode, the object might be a path
                if ($this->n3Mode) {
                    return \call_user_func($this->getPathReader, \call_user_func($this->getContextEndReader));
                }
            }

            return \call_user_func($this->getContextEndReader);
        };

        // ### `_readPredicateOrNamedGraph` reads a triple's predicate, or a named graph
        $this->readPredicateOrNamedGraph = function ($token) {
            return '{' === $token['type'] ? \call_user_func($this->readGraph, $token) : \call_user_func($this->readPredicate, $token);
        };

        // ### `_readGraph` reads a graph
        $this->readGraph = function ($token) {
            if ('{' !== $token['type']) {
                return \call_user_func($this->error, 'Expected graph but got '.$token['type'], $token);
            }
            // The "subject" we read is actually the GRAPH's label
            $this->graph = $this->subject;
            $this->subject = null;

            return $this->readSubject;
        };

        // ### `_readBlankNodeHead` reads the head of a blank node
        $this->readBlankNodeHead = function ($token) {
            if (']' === $token['type']) {
                $this->subject = null;

                return \call_user_func($this->readBlankNodeTail, $token);
            } else {
                $this->predicate = null;

                return \call_user_func($this->readPredicate, $token);
            }
        };

        // ### `_readBlankNodeTail` reads the end of a blank node
        $this->readBlankNodeTail = function ($token) {
            if (']' !== $token['type']) {
                return \call_user_func($this->readBlankNodePunctuation, $token);
            }

            // Store blank node triple
            if (null !== $this->subject) {
                \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
            }

            // Restore the parent context containing this blank node
            $empty = null === $this->predicate;
            $this->restoreContext();
            // If the blank node was the subject, continue reading the predicate
            if (null === $this->object) {
                // If the blank node was empty, it could be a named graph label
                return $empty ? $this->readPredicateOrNamedGraph : $this->readPredicateAfterBlank;
            }
            // If the blank node was the object, restore previous context and read punctuation
            else {
                return \call_user_func($this->getContextEndReader);
            }
        };

        // ### `_readPredicateAfterBlank` reads a predicate after an anonymous blank node
        $this->readPredicateAfterBlank = function ($token) {
            // If a dot follows a blank node in top context, there is no predicate
            if ('.' === $token['type'] && 0 === \count($this->contextStack)) {
                $this->subject = null; // cancel the current triple

                return \call_user_func($this->readPunctuation, $token);
            }

            return \call_user_func($this->readPredicate, $token);
        };

        // ### `_readListItem` reads items from a list
        $this->readListItem = function ($token) {
            $item = null;                        // The item of the list
            $list = null;                        // The list itself
            $prevList = $this->subject;          // The previous list that contains this list
            $stack = &$this->contextStack;        // The stack of parent contexts
            $parent = &$stack[\count($stack) - 1]; // The parent containing the current list
            $next = $this->readListItem;         // The next function to execute
            $itemComplete = true;                // Whether the item has been read fully

            switch ($token['type']) {
                case '[':
                    // Stack the current list triple and start a new triple with a blank node as subject
                    $list = '_:b'.$this->blankNodeCount++;
                    $item = '_:b'.$this->blankNodeCount++;
                    $this->subject = $item;
                    $this->saveContext('blank', $this->graph, $list, self::RDF_FIRST, $this->subject);
                    $next = $this->readBlankNodeHead;
                    break;
                case '(':
                    // Stack the current list triple and start a new list
                    $this->saveContext('list', $this->graph, $list = '_:b'.$this->blankNodeCount++, self::RDF_FIRST, self::RDF_NIL);
                    $this->subject = null;
                    break;
                case ')':
                    // Closing the list; restore the parent context
                    $this->restoreContext();
                    // If this list is contained within a parent list, return the membership triple here.
                    // This will be `<parent list element> rdf:first <this list>.`.
                    if (0 !== \count($stack) && 'list' === $stack[\count($stack) - 1]['type']) {
                        \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
                    }
                    // Was this list the parent's subject?
                    if (null === $this->predicate) {
                        // The next token is the predicate
                        $next = $this->readPredicate;
                        // No list tail if this was an empty list
                        if (self::RDF_NIL === $this->subject) {
                            return $next;
                        }
                    }
                    // The list was in the parent context's object
                    else {
                        $next = \call_user_func($this->getContextEndReader);
                        // No list tail if this was an empty list
                        if (self::RDF_NIL === $this->object) {
                            return $next;
                        }
                    }
                    // Close the list by making the head nil
                    $list = self::RDF_NIL;
                    break;
                case 'literal':
                    $item = $token['value'];
                    $itemComplete = false; // Can still have a datatype or language
                    $next = $this->readListItemDataTypeOrLang;
                    break;
                default:
                    $item = \call_user_func($this->readEntity, $token);
                    if (null == $item) {
                        return;
                    }
            }

            // Create a new blank node if no item head was assigned yet
            if (null === $list) {
                $list = '_:b'.$this->blankNodeCount++;
                $this->subject = $list;
            }
            // Is this the first element of the list?
            if (null === $prevList) {
                // This list is either the subject or the object of its parent
                if (null === $parent['predicate']) {
                    $parent['subject'] = $list;
                } else {
                    $parent['object'] = $list;
                }
            } else {
                // Continue the previous list with the current list
                \call_user_func($this->triple, $prevList, self::RDF_REST, $list, $this->graph);
            }
            // Add the item's value
            if (null !== $item) {
                // In N3 mode, the item might be a path
                if ($this->n3Mode && ('IRI' === $token['type'] || 'prefixed' === $token['type'])) {
                    // Create a new context to add the item's path
                    $this->saveContext('item', $this->graph, $list, self::RDF_FIRST, $item);
                    $this->subject = $item;
                    $this->predicate = null;
                    // _readPath will restore the context and output the item
                    return \call_user_func($this->getPathReader, $this->readListItem);
                }
                // Output the item if it is complete
                if ($itemComplete) {
                    \call_user_func($this->triple, $list, self::RDF_FIRST, $item, $this->graph);
                }
                // Otherwise, save it for completion
                else {
                    $this->object = $item;
                }
            }

            return $next;
        };

        // ### `_readDataTypeOrLang` reads an _optional_ data type or language
        $this->readDataTypeOrLang = function ($token) {
            return \call_user_func($this->completeLiteral, $token, false);
        };

        // ### `_readListItemDataTypeOrLang` reads an _optional_ data type or language in a list
        $this->readListItemDataTypeOrLang = function ($token) {
            return \call_user_func($this->completeLiteral, $token, true);
        };

        // ### `_completeLiteral` completes the object with a data type or language
        $this->completeLiteral = function ($token, $listItem) {
            $suffix = false;
            switch ($token['type']) {
                // Add a "^^type" suffix for types (IRIs and blank nodes)
                case 'type':
                case 'typeIRI':
                    $suffix = true;
                    $this->object .= '^^'.\call_user_func($this->readEntity, $token);
                    break;
                    // Add an "@lang" suffix for language tags
                case 'langcode':
                    $suffix = true;
                    $this->object .= '@'.strtolower($token['value']);
                    break;
            }
            // If this literal was part of a list, write the item
            // (we could also check the context stack, but passing in a flag is faster)
            if ($listItem) {
                \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $this->object, $this->graph);
            }
            // Continue with the rest of the input
            if ($suffix) {
                return \call_user_func($this->getContextEndReader);
            } else {
                $this->readCallback = \call_user_func($this->getContextEndReader);

                return \call_user_func($this->readCallback, $token);
            }
        };

        // ### `_readFormulaTail` reads the end of a formula
        $this->readFormulaTail = function ($token) {
            if ('}' !== $token['type']) {
                return \call_user_func($this->readPunctuation, $token);
            }

            // Store the last triple of the formula
            if (isset($this->subject)) {
                \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
            }

            // Restore the parent context containing this formula
            $this->restoreContext();
            // If the formula was the subject, continue reading the predicate.
            // If the formula was the object, read punctuation.
            return !isset($this->object) ? $this->readPredicate : \call_user_func($this->getContextEndReader);
        };

        // ### `_readPunctuation` reads punctuation between triples or triple parts
        $this->readPunctuation = function ($token) {
            $next = null;
            $subject = isset($this->subject) ? $this->subject : null;
            $graph = $this->graph;
            $inversePredicate = $this->inversePredicate;
            switch ($token['type']) {
                // A closing brace ends a graph
                case '}':
                    if (null === $this->graph) {
                        return \call_user_func($this->error, 'Unexpected graph closing', $token);
                    }
                    if ($this->n3Mode) {
                        return \call_user_func($this->readFormulaTail, $token);
                    }
                    $this->graph = null;
                    // A dot just ends the statement, without sharing anything with the next
                    // no break
                case '.':
                    $this->subject = null;
                    $next = \count($this->contextStack) ? $this->readSubject : $this->readInTopContext;
                    if ($inversePredicate) {
                        $this->inversePredicate = false;
                    } //TODO: What’s this?
                    break;
                    // Semicolon means the subject is shared; predicate and object are different
                case ';':
                    $next = $this->readPredicate;
                    break;
                    // Comma means both the subject and predicate are shared; the object is different
                case ',':
                    $next = $this->readObject;
                    break;
                default:
                    // An entity means this is a quad (only allowed if not already inside a graph)
                    $graph = \call_user_func($this->readEntity, $token);
                    if ($this->supportsQuads && null === $this->graph && $graph) {
                        $next = $this->readQuadPunctuation;
                        break;
                    }

                    return \call_user_func($this->error, 'Expected punctuation to follow "'.$this->object.'"', $token);
            }
            // A triple has been completed now, so return it
            if (null !== $subject) {
                $predicate = $this->predicate;
                $object = $this->object;
                if (!$inversePredicate) {
                    \call_user_func($this->triple, $subject, $predicate, $object, $graph);
                } else {
                    \call_user_func($this->triple, $object, $predicate, $subject, $graph);
                }
            }

            return $next;
        };

        // ### `_readBlankNodePunctuation` reads punctuation in a blank node
        $this->readBlankNodePunctuation = function ($token) {
            $next = null;
            switch ($token['type']) {
                // Semicolon means the subject is shared; predicate and object are different
                case ';':
                    $next = $this->readPredicate;
                    break;
                    // Comma means both the subject and predicate are shared; the object is different
                case ',':
                    $next = $this->readObject;
                    break;
                default:
                    return \call_user_func($this->error, 'Expected punctuation to follow "'.$this->object.'"', $token);
            }
            // A triple has been completed now, so return it
            \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);

            return $next;
        };

        // ### `_readQuadPunctuation` reads punctuation after a quad
        $this->readQuadPunctuation = function ($token) {
            if ('.' !== $token['type']) {
                return \call_user_func($this->error, 'Expected dot to follow quad', $token);
            }

            return $this->readInTopContext;
        };

        // ### `_readPrefix` reads the prefix of a prefix declaration
        $this->readPrefix = function ($token) {
            if ('prefix' !== $token['type']) {
                return \call_user_func($this->error, 'Expected prefix to follow @prefix', $token);
            }
            $this->prefix = $token['value'];

            return $this->readPrefixIRI;
        };

        // ### `_readPrefixIRI` reads the IRI of a prefix declaration
        $this->readPrefixIRI = function ($token) {
            if ('IRI' !== $token['type']) {
                return \call_user_func($this->error, 'Expected IRI to follow prefix "'.$this->prefix.':"', $token);
            }
            $prefixIRI = \call_user_func($this->readEntity, $token);
            $this->prefixes[$this->prefix] = $prefixIRI;
            \call_user_func($this->prefixCallback, $this->prefix, $prefixIRI);

            return $this->readDeclarationPunctuation;
        };

        // ### `_readBaseIRI` reads the IRI of a base declaration
        $this->readBaseIRI = function ($token) {
            if ('IRI' !== $token['type']) {
                return \call_user_func($this->error, 'Expected IRI to follow base declaration', $token);
            }
            $this->setBase(null === $this->base || preg_match($this->absoluteIRI, $token['value']) ?
            $token['value'] : \call_user_func($this->resolveIRI, $token));

            return $this->readDeclarationPunctuation;
        };

        // ### `_readNamedGraphLabel` reads the label of a named graph
        $this->readNamedGraphLabel = function ($token) {
            switch ($token['type']) {
                case 'IRI':
                case 'blank':
                case 'prefixed':
                \call_user_func($this->readSubject, $token);

                return $this->readGraph;
                case '[':
                return $this->readNamedGraphBlankLabel;
                default:
                return \call_user_func($this->error, 'Invalid graph label', $token);
            }
        };

        // ### `_readNamedGraphLabel` reads a blank node label of a named graph
        $this->readNamedGraphBlankLabel = function ($token) {
            if (']' !== $token['type']) {
                return \call_user_func($this->error, 'Invalid graph label', $token);
            }
            $this->subject = '_:b'.$this->blankNodeCount++;

            return $this->readGraph;
        };

        // ### `_readDeclarationPunctuation` reads the punctuation of a declaration
        $this->readDeclarationPunctuation = function ($token) {
            // SPARQL-style declarations don't have punctuation
            if ($this->sparqlStyle) {
                $this->sparqlStyle = false;

                return \call_user_func($this->readInTopContext, $token);
            }

            if ('.' !== $token['type']) {
                return \call_user_func($this->error, 'Expected declaration to end with a dot', $token);
            }

            return $this->readInTopContext;
        };

        // Reads a list of quantified symbols from a @forSome or @forAll statement
        $this->readQuantifierList = function ($token) {
            $entity = null;
            switch ($token['type']) {
                case 'IRI':
                case 'prefixed':
                    $entity = \call_user_func($this->readEntity, $token, true);
                    break;
                default:
                    return \call_user_func($this->error, 'Unexpected '.$token['type'], $token);
            }
            // Without explicit quantifiers, map entities to a quantified entity
            if (!$this->explicitQuantifiers) {
                $this->quantified[$entity] = $this->quantifiedPrefix.$this->blankNodeCount++;
            } else {
                // With explicit quantifiers, output the reified quantifier
                // If this is the first item, start a new quantifier list
                if (null === $this->subject) {
                    $this->subject = '_:b'.$this->blankNodeCount++;
                    \call_user_func($this->triple, isset($this->graph) ? $this->graph : '', $this->predicate, $this->subject, self::QUANTIFIERS_GRAPH);
                }
                // Otherwise, continue the previous list
                else {
                    \call_user_func($this->triple,$this->subject, self::RDF_REST,
                    $this->subject = '_:b'.$this->blankNodeCount++, self::QUANTIFIERS_GRAPH);
                }
                // Output the list item
                \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $entity, self::QUANTIFIERS_GRAPH);
            }

            return $this->readQuantifierPunctuation;
        };

        // Reads punctuation from a @forSome or @forAll statement
        $this->readQuantifierPunctuation = function ($token) {
            // Read more quantifiers
            if (',' === $token['type']) {
                return $this->readQuantifierList;
            }
            // End of the quantifier list
            else {
                // With explicit quantifiers, close the quantifier list
                if ($this->explicitQuantifiers) {
                    \call_user_func($this->triple, $this->subject, self::RDF_REST, self::RDF_NIL, self::QUANTIFIERS_GRAPH);
                    $this->subject = null;
                }
                // Read a dot
                $this->readCallback = \call_user_func($this->getContextEndReader);

                return \call_user_func($this->readCallback, $token);
            }
        };

        // ### `_getPathReader` reads a potential path and then resumes with the given function
        $this->getPathReader = function ($afterPath): ?callable {
            $this->afterPath = $afterPath;

            return $this->readPath;
        };

        // ### `_readPath` reads a potential path
        $this->readPath = function ($token): ?callable {
            switch ($token['type']) {
                case '!':
                    // Forward path
                    return $this->readForwardPath;
                case '^':
                    // Backward path
                    return $this->readBackwardPath;
                default:
                    // Not a path; resume reading where we left off
                    $stack = $this->contextStack;
                    $parent = null;
                    if (\is_array($stack) && \count($stack) - 1 > 0 && isset($stack[\count($stack) - 1])) {
                        $parent = $stack[\count($stack) - 1];
                    }
                    // If we were reading a list item, we still need to output it
                    if ($parent && 'item' === $parent['type']) {
                        // The list item is the remaining subejct after reading the path
                        $item = $this->subject;
                        // Switch back to the context of the list
                        $this->restoreContext();
                        // Output the list item
                        \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $item, $this->graph);
                    }

                    return \call_user_func($this->afterPath, $token);
            }
        };

        // ### `_readForwardPath` reads a '!' path
        $this->readForwardPath = function ($token) {
            $subject = null;
            $predicate = null;
            $object = '_:b'.$this->blankNodeCount++;
            // The next token is the predicate
            $predicate = \call_user_func($this->readEntity, $token);
            if (!$predicate) {
                return;
            }
            // If we were reading a subject, replace the subject by the path's object
            if (null === $this->predicate) {
                $subject = $this->subject;
                $this->subject = $object;
            }
            // If we were reading an object, replace the subject by the path's object
            else {
                $subject = $this->object;
                $this->object = $object;
            }
            // Emit the path's current triple and read its next section
            \call_user_func($this->triple, $subject, $predicate, $object, $this->graph);

            return $this->readPath;
        };

        // ### `_readBackwardPath` reads a '^' path
        $this->readBackwardPath = function ($token) {
            $subject = '_:b'.$this->blankNodeCount++;
            $predicate = null;
            $object = null;
            // The next token is the predicate
            $predicate = \call_user_func($this->readEntity, $token);
            if ($predicate) {
                return;
            }
            // If we were reading a subject, replace the subject by the path's subject
            if (null === $this->predicate) {
                $object = $this->subject;
                $this->subject = $subject;
            }
            // If we were reading an object, replace the subject by the path's subject
            else {
                $object = $this->object;
                $this->object = $subject;
            }
            // Emit the path's current triple and read its next section
            \call_user_func($this->triple, $subject, $predicate, $object, $this->graph);

            return $this->readPath;
        };

        // ### `_getContextEndReader` gets the next reader function at the end of a context
        $this->getContextEndReader = function () {
            $contextStack = $this->contextStack;
            if (!\count($contextStack)) {
                return $this->readPunctuation;
            }

            switch ($contextStack[\count($contextStack) - 1]['type']) {
                case 'blank':
                    return $this->readBlankNodeTail;
                case 'list':
                    return $this->readListItem;
                case 'formula':
                    return $this->readFormulaTail;
            }
        };

        // ### `_triple` emits a triple through the callback
        $this->triple = function ($subject, $predicate, $object, $graph) {
            \call_user_func($this->callback, null, ['subject' => $subject, 'predicate' => $predicate, 'object' => $object, 'graph' => isset($graph) ? $graph : '']);
        };

        // ### `_error` emits an error message through the callback
        $this->error = function ($message, $token) {
            if ($this->callback) {
                \call_user_func($this->callback, new \Exception($message.' on line '.$token['line'].'.'), null);
            } else {
                throw new \Exception($message.' on line '.$token['line'].'.');
            }
        };

        // ### `_resolveIRI` resolves a relative IRI token against the base path,
        // assuming that a base path has been set and that the IRI is indeed relative
        $this->resolveIRI = function ($token) {
            $iri = $token['value'];

            if (!isset($iri[0])) { // An empty relative IRI indicates the base IRI
                return $this->base;
            }

            switch ($iri[0]) {
                // Resolve relative fragment IRIs against the base IRI
                case '#': return $this->base.$iri;
                // Resolve relative query string IRIs by replacing the query string
                case '?': //should only replace the first occurence
                    return preg_replace('/(?:\?.*)?$/', $iri, $this->base, 1);
                // Resolve root-relative IRIs at the root of the base IRI
                case '/':
                // Resolve scheme-relative IRIs to the scheme
                    return ('/' === $iri[1] ? $this->baseScheme : $this->baseRoot).\call_user_func($this->removeDotSegments, $iri);
                // Resolve all other IRIs at the base IRI's path
                default:
                    return \call_user_func($this->removeDotSegments, $this->basePath.$iri);
            }
        };

        // ### `_removeDotSegments` resolves './' and '../' path segments in an IRI as per RFC3986
        $this->removeDotSegments = function ($iri) {
            // Don't modify the IRI if it does not contain any dot segments
            if (!preg_match($this->dotSegments, $iri)) {
                return $iri;
            }

            // Start with an imaginary slash before the IRI in order to resolve trailing './' and '../'
            $result = '';
            $length = \strlen($iri);
            $i = -1;
            $pathStart = -1;
            $segmentStart = 0;
            $next = '/';

            // a function we will need here to fetch the last occurence
            //search backwards for needle in haystack, and return its position
            $rstrpos = function ($haystack, $needle) {
                $size = \strlen($haystack);
                $pos = strpos(strrev($haystack), $needle);
                if (false === $pos) {
                    return false;
                }

                return $size - $pos - 1;
            };

            while ($i < $length) {
                switch ($next) {
                    // The path starts with the first slash after the authority
                    case ':':
                        if ($pathStart < 0) {
                            // Skip two slashes before the authority
                            if ('/' === $iri[++$i] && '/' === $iri[++$i]) {
                                // Skip to slash after the authority
                                while (($pathStart = $i + 1) < $length && '/' !== $iri[$pathStart]) {
                                    $i = $pathStart;
                                }
                            }
                        }
                        break;
                        // Don't modify a query string or fragment
                    case '?':
                    case '#':
                        $i = $length;
                        break;
                    // Handle '/.' or '/..' path segments
                    case '/':
                        if (isset($iri[$i + 1]) && '.' === $iri[$i + 1]) {
                            if (isset($iri[++$i + 1])) {
                                $next = $iri[$i + 1];
                            } else {
                                $next = null;
                            }
                            switch ($next) {
                                // Remove a '/.' segment
                                case '/':
                                    if (($i - 1 - $segmentStart) > 0) {
                                        $result .= substr($iri, $segmentStart, $i - 1 - $segmentStart);
                                    }
                                    $segmentStart = $i + 1;
                                    break;
                                    // Remove a trailing '/.' segment
                                case null:
                                case '?':
                                case '#':
                                    return $result.substr($iri, $segmentStart, $i - $segmentStart).substr($iri, $i + 1);
                                    // Remove a '/..' segment
                                case '.':
                                    if (isset($iri[++$i + 1])) {
                                        $next = $iri[$i + 1];
                                    } else {
                                        $next = null;
                                    }
                                    if (null === $next || '/' === $next || '?' === $next || '#' === $next) {
                                        if ($i - 2 - $segmentStart > 0) {
                                            $result .= substr($iri, $segmentStart, $i - 2 - $segmentStart);
                                        }
                                        // Try to remove the parent path from result
                                        if (($segmentStart = $rstrpos($result, '/')) >= $pathStart) {
                                            $result = substr($result, 0, $segmentStart);
                                        }
                                        // Remove a trailing '/..' segment
                                        if ('/' !== $next) {
                                            return $result.'/'.substr($iri, $i + 1);
                                        }
                                        $segmentStart = $i + 1;
                                    }
                            }
                        }
                }
                if (++$i < $length) {
                    $next = $iri[$i];
                }
            }

            return $result.substr($iri, $segmentStart);
        };
    }

    // ## Public methods

    // ### `parse` parses the N3 input and emits each parsed triple through the callback
    public function parse($input, $tripleCallback = null, $prefixCallback = null)
    {
        $this->setTripleCallback($tripleCallback);
        $this->setPrefixCallback($prefixCallback);

        return $this->parseChunk($input, true);
    }

    // ### New method for streaming possibilities: parse only a chunk
    public function parseChunk($input, $finalize = false)
    {
        if (!isset($this->tripleCallback)) {
            $triples = [];
            $error = null;
            $this->callback = function ($e, $t = null) use (&$triples, &$error) {
                if (!$e && $t) {
                    $triples[] = $t;
                } elseif (!$e) {
                    //DONE
                } else {
                    $error = $e;
                }
            };
            $tokens = $this->lexer->tokenize($input, $finalize);
            foreach ($tokens as $token) {
                if (isset($this->readCallback)) {
                    $this->readCallback = \call_user_func($this->readCallback, $token);
                }
            }
            if ($error) {
                throw $error;
            }

            return $triples;
        } else {
            // Parse asynchronously otherwise, executing the read callback when a token arrives
            $this->callback = $this->tripleCallback;
            try {
                $tokens = $this->lexer->tokenize($input, $finalize);
                foreach ($tokens as $token) {
                    if (isset($this->readCallback)) {
                        $this->readCallback = \call_user_func($this->readCallback, $token);
                    } else {
                        //error occured in parser
                        break;
                    }
                }
            } catch (\Exception $e) {
                if ($this->callback) {
                    \call_user_func($this->callback, $e, null);
                } else {
                    throw $e;
                }
                $this->callback = function () {};
            }
        }
    }

    public function setTripleCallback($tripleCallback = null)
    {
        $this->tripleCallback = $tripleCallback;
    }

    public function setPrefixCallback($prefixCallback = null)
    {
        if (isset($prefixCallback)) {
            $this->prefixCallback = $prefixCallback;
        } else {
            $this->prefixCallback = function () {};
        }
    }

    public function end()
    {
        return $this->parseChunk('', true);
    }
}
