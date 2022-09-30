<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/**
 * a clone of the N3Lexer class from the N3js code by Ruben Verborgh
 *
 * N3Lexer tokenizes N3 documents.
 */
class N3Lexer
{
    // Regular expression and replacement string to escape N3 strings.
    // Note how we catch invalid unicode sequences separately (they will trigger an error).
    private $escapeSequence = '/\\\\u([a-fA-F0-9]{4})|\\\\U([a-fA-F0-9]{8})|\\\\[uU]|\\\\(.)/';
    private $escapeReplacements;
    private $illegalIriChars = '/[\x00-\x20<>\\"\{\}\|\^\`]/';

    private $input;
    private $line = 1;

    /**
     * @var array|null
     */
    private $comments;
    private $n3Mode;
    private $prevTokenType;

    private $_oldTokenize;
    private $_tokenize;

    public function __construct($options = [])
    {
        $this->initTokenize();
        $this->escapeReplacements = [
            '\\' => '\\', "'" => "'", '"' => '"',
            'n' => "\n", 'r' => "\r", 't' => "\t", 'f' => "\f", 'b' => \chr(8),
            '_' => '_', '~' => '~', '.' => '.', '-' => '-', '!' => '!', '$' => '$', '&' => '&',
            '(' => '(', ')' => ')', '*' => '*', '+' => '+', ',' => ',', ';' => ';', '=' => '=',
            '/' => '/', '?' => '?', '#' => '#', '@' => '@', '%' => '%',
        ];
        // In line mode (N-Triples or N-Quads), only simple features may be parsed
        if ($options['lineMode']) {
            // Don't tokenize special literals
            $this->tripleQuotedString = '/$0^/';
            $this->number = '/$0^/';
            $this->boolean = '/$0^/';
            // Swap the tokenize method for a restricted version
            $this->_oldTokenize = $this->_tokenize;
            $self = $this;
            $this->_tokenize = function ($input, $finalize = true) use ($self) {
                $tokens = \call_user_func($this->_oldTokenize, $input, $finalize);
                foreach ($tokens as $token) {
                    if (!preg_match('/^(?:IRI|prefixed|literal|langcode|type|\.|eof)$/', $token['type'])) {
                        throw $self->syntaxError($token['type'], $token['line']);
                    }
                }

                return $tokens;
            };
        }
        // Enable N3 functionality by default
        $this->n3Mode = false !== $options['n3'];

        // Disable comment tokens by default
        $this->comments = isset($options['comments']) ? $options['comments'] : null;
    }

    // ## Regular expressions
    //_iri:        /^<((?:[^ <>{}\\]|\\[uU])+)>[ \t]*/, // IRI with escape sequences; needs sanity check after unescaping
    private $iri = '/^<((?:[^ <>{}\\\\]|\\\\[uU])+)>[ \\t]*/'; // IRI with escape sequences; needs sanity check after unescaping
    //      _unescapedIri:    /^<([^\x00-\x20<>\\"\{\}\|\^\`]*)>[ \t]*/, // IRI without escape sequences; no unescaping
    private $unescapedIri = '/^<([^\\x00-\\x20<>\\\\"\\{\\}\\|\\^\\`]*)>[ \\t]*/'; // IRI without escape sequences; no unescaping
    //  _unescapedString:      /^"[^"\\]+"(?=[^"\\])/, // non-empty string without escape sequences
    private $unescapedString = '/^"[^\\\\"]+"(?=[^\\\\"])/'; // non-empty string without escape sequences
    //  _singleQuotedString:      /^"[^"\\]*(?:\\.[^"\\]*)*"(?=[^"\\])|^'[^'\\]*(?:\\.[^'\\]*)*'(?=[^'\\])/,
    private $singleQuotedString = '/^"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"(?=[^"\\\\])|^\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'(?=[^\'\\\\])/';
    //  _tripleQuotedString:       /^""("[^"\\]*(?:(?:\\.|"(?!""))[^"\\]*)*")""|^''('[^'\\]*(?:(?:\\.|'(?!''))[^'\\]*)*')''/,
    private $tripleQuotedString = '/^""("[^\\\\"]*(?:(?:\\\\.|"(?!""))[^\\\\"]*)*")""|^\'\'(\'[^\\\\\']*(?:(?:\\\\.|\'(?!\'\'))[^\\\\\']*)*\')\'\'/';
    private $langcode = '/^@([a-z]+(?:-[a-z0-9]+)*)(?=[^a-z0-9\\-])/i';
    private $prefix = '/^((?:[A-Za-z\\xc0-\\xd6\\xd8-\\xf6])(?:\\.?[\\-0-9A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6])*)?:(?=[#\\s<])/';
    private $prefixed = "/^((?:[A-Za-z\\xc0-\\xd6\\xd8-\\xf6\\xf8-\\x{02ff}\\x{0370}-\\x{037d}\\x{037f}-\\x{1fff}\\x{200c}\\x{200d}\\x{2070}-\\x{218f}\\x{2c00}-\\x{2fef}\\x{3001}-\\x{d7ff}\\x{f900}-\\x{fdcf}\\x{fdf0}-\\x{fffd}])(?:\\.?[\\-0-9A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6\\xf8-\\x{037d}\\x{037f}-\\x{1fff}\\x{200c}\\x{200d}\\x{203f}\\x{2040}\\x{2070}-\\x{218f}\\x{2c00}-\\x{2fef}\\x{3001}-\\x{d7ff}\\x{f900}-\\x{fdcf}\\x{fdf0}-\\x{fffd}])*)?:((?:(?:[0-:A-Z_a-z\\xc0-\\xd6\\xd8-\\xf6\\xf8-\\x{02ff}\\x{0370}-\\x{037d}\\x{037f}-\\x{1fff}\\x{200c}\\x{200d}\\x{2070}-\\x{218f}\\x{2c00}-\\x{2fef}\\x{3001}-\\x{d7ff}\\x{f900}-\\x{fdcf}\\x{fdf0}-\\x{fffd}]|%[0-9a-fA-F]{2}|\\\\[!#-\\/;=?\\-@_~])(?:(?:[\\.\\-0-:A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6\\xf8-\\x{037d}\\x{037f}-\\x{1fff}\\x{200c}\\x{200d}\\x{203f}\\x{2040}\\x{2070}-\\x{218f}\\x{2c00}-\\x{2fef}\\x{3001}-\\x{d7ff}\\x{f900}-\\x{fdcf}\\x{fdf0}-\\x{fffd}]|%[0-9a-fA-F]{2}|\\\\[!#-\\/;=?\\-@_~])*(?:[\\-0-:A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6\\xf8-\\x{037d}\\x{037f}-\\x{1fff}\\x{200c}\\x{200d}\\x{203f}\\x{2040}\\x{2070}-\\x{218f}\\x{2c00}-\\x{2fef}\\x{3001}-\\x{d7ff}\\x{f900}-\\x{fdcf}\\x{fdf0}-\\x{fffd}]|%[0-9a-fA-F]{2}|\\\\[!#-\\/;=?\\-@_~]))?)?)(?:[ \\t]+|(?=\\.?[,;!\\^\\s#()\\[\\]\\{\\}\"'<]))/u";

    private $variable = '/^\\?(?:(?:[A-Z_a-z\\xc0-\\xd6\\xd8-\\xf6])(?:[\\-0-:A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6])*)(?=[.,;!\\^\\s#()\\[\\]\\{\\}"\'<])/';

    private $blank = '/^_:((?:[0-9A-Z_a-z\\xc0-\\xd6\\xd8-\\xf6])(?:\\.?[\\-0-9A-Z_a-z\\xb7\\xc0-\\xd6\\xd8-\\xf6])*)(?:[ \\t]+|(?=\\.?[,;:\\s#()\\[\\]\\{\\}"\'<]))/';
    private $number = "/^[\\-+]?(?:\\d+\\.?\\d*([eE](?:[\\-\\+])?\\d+)|\\d*\\.?\\d+)(?=[.,;:\\s#()\\[\\]\\{\\}\"'<])/";
    private $boolean = '/^(?:true|false)(?=[.,;\\s#()\\[\\]\\{\\}"\'<])/';
    private $keyword = '/^@[a-z]+(?=[\\s#<])/i';
    private $sparqlKeyword = '/^(?:PREFIX|BASE|GRAPH)(?=[\\s#<])/i';
    private $shortPredicates = '/^a(?=\\s+|<)/';
    private $newline = '/^[ \\t]*(?:#[^\\n\\r]*)?(?:\\r\\n|\\n|\\r)[ \\t]*/';
    private $comment = '/#([^\\n\\r]*)/';
    private $whitespace = '/^[ \\t]+/';
    private $endOfFile = '/^(?:#[^\\n\\r]*)?$/';

    /**
     * tokenizes as for as possible, emitting tokens through the callback
     */
    private function tokenizeToEnd($callback, $inputFinished)
    {
        // Continue parsing as far as possible; the loop will return eventually
        $input = $this->input;

        // Signals the syntax error through the callback
        $reportSyntaxError = function ($self) use ($callback, &$input) {
            preg_match("/^\S*/", $input, $match);
            $callback($self->syntaxError($match[0], $self->line), null);
        };

        $outputComments = $this->comments;
        while (true) {
            // Count and skip whitespace lines
            $whiteSpaceMatch = null;
            $comment = null;
            while (preg_match($this->newline, $input, $whiteSpaceMatch)) {
                // Try to find a comment
                if ($outputComments && preg_match($this->comment, $whiteSpaceMatch[0], $comment)) {
                    /*
                     * originally the following line was here:
                     *
                     *      callback(null, ['line' => $this->line, 'type' => 'comment', 'value' => $comment[1], 'prefix' => '']);
                     *
                     * but it makes no sense, because callback is a function from PHPUnit, which can't be relied on
                     * in this context. therefore this line must be at least commented out. the question is, if the
                     * whole "case" can be removed as well.
                     *
                     * FYI: #29
                     */
                }
                // Advance the input
                $input = substr($input, \strlen($whiteSpaceMatch[0]), \strlen($input));
                ++$this->line;
            }
            // Skip whitespace on current line
            if (preg_match($this->whitespace, $input, $whiteSpaceMatch)) {
                $input = substr($input, \strlen($whiteSpaceMatch[0]), \strlen($input));
            }

            // Stop for now if we're at the end
            if (preg_match($this->endOfFile, $input)) {
                // If the $input is finished, emit EOF
                if ($inputFinished) {
                    // Try to find a final comment
                    if ($outputComments && preg_match($this->comment, $input, $comment)) {
                        $callback(null, ['line' => $this->line, 'type' => 'comment', 'value' => $comment[1], 'prefix' => '']);
                    }
                    $callback($input = null, ['line' => $this->line, 'type' => 'eof', 'value' => '', 'prefix' => '']);
                }
                $this->input = $input;

                return $input;
            }

            // Look for specific token types based on the first character
            $line = $this->line;
            $type = '';
            $value = '';
            $prefix = '';
            $firstChar = $input[0];
            $match = null;
            $matchLength = 0;
            $unescaped = null;
            $inconclusive = false;

            switch ($firstChar) {
                case '^':
                    // We need at least 3 tokens lookahead to distinguish ^^<IRI> and ^^pre:fixed
                    if (\strlen($input) < 3) {
                        break;
                    }
                    // Try to match a type
                    elseif ('^' === $input[1]) {
                        $this->prevTokenType = '^^';
                        // Move to type IRI or prefixed name
                        $input = substr($input, 2);
                        if ('<' !== $input[0]) {
                            $inconclusive = true;
                            break;
                        }
                    }
                    // If no type, it must be a path expression
                    else {
                        if ($this->n3Mode) {
                            $matchLength = 1;
                            $type = '^';
                        }
                        break;
                    }
                    // Fall through in case the type is an IRI
                    // no break
                case '<':
                    // Try to find a full IRI without escape sequences
                    if (preg_match($this->unescapedIri, $input, $match)) {
                        $type = 'IRI';
                        $value = $match[1];
                    }

                    // Try to find a full IRI with escape sequences
                    elseif (preg_match($this->iri, $input, $match)) {
                        $unescaped = $this->unescape($match[1]);
                        if (null === $unescaped || preg_match($this->illegalIriChars, $unescaped)) {
                            return $reportSyntaxError($this);
                        }
                        $type = 'IRI';
                        $value = $unescaped;
                    }
                    // Try to find a backwards implication arrow
                    elseif ($this->n3Mode && \strlen($input) > 1 && '=' === $input[1]) {
                        $type = 'inverse';
                        $matchLength = 2;
                        $value = 'http://www.w3.org/2000/10/swap/log#implies';
                    }
                    break;
                case '_':
                    // Try to find a blank node. Since it can contain (but not end with) a dot,
                    // we always need a non-dot character before deciding it is a prefixed name.
                    // Therefore, try inserting a space if we're at the end of the $input.
                    if ((preg_match($this->blank, $input, $match)) || $inputFinished && (preg_match($this->blank, $input.' ', $match))) {
                        $type = 'blank';
                        $prefix = '_';
                        $value = $match[1];
                    }

                    break;

                case '"':
                case "'":
                    // Try to find a non-empty double-quoted literal without escape sequences
                    if (preg_match($this->unescapedString, $input, $match)) {
                        $type = 'literal';
                        $value = $match[0];
                    }
                    // Try to find any other literal wrapped in a pair of single or double quotes
                    elseif (preg_match($this->singleQuotedString, $input, $match)) {
                        $unescaped = $this->unescape($match[0]);
                        if (null === $unescaped) {
                            return $reportSyntaxError($this);
                        }
                        $type = 'literal';
                        $value = preg_replace('/^\'|\'$/', '"', $unescaped);
                    }
                    // Try to find a literal wrapped in three pairs of single or double quotes
                    elseif (preg_match($this->tripleQuotedString, $input, $match)) {
                        $unescaped = isset($match[1]) ? $match[1] : $match[2];
                        // Count the newlines and advance line counter
                        $this->line += \count(preg_split('/\r\n|\r|\n/', $unescaped)) - 1;
                        $unescaped = $this->unescape($unescaped);
                        if (null === $unescaped) {
                            return $reportSyntaxError($this);
                        }
                        $type = 'literal';
                        $value = preg_replace("/^'|'$/", '"', $unescaped);
                    }
                break;

                case '?':
                    // Try to find a variable
                    if ($this->n3Mode && (preg_match($this->variable, $input, $match))) {
                        $type = 'var';
                        $value = $match[0];
                    }
                    break;

                case '@':
                    // Try to find a language code
                    if ('literal' === $this->prevTokenType && preg_match($this->langcode, $input, $match)) {
                        $type = 'langcode';
                        $value = $match[1];
                    }

                    // Try to find a keyword
                    elseif (preg_match($this->keyword, $input, $match)) {
                        $type = $match[0];
                    }
                    break;

                case '.':
                    // Try to find a dot as punctuation
                    if (1 === \strlen($input) ? $inputFinished : ($input[1] < '0' || $input[1] > '9')) {
                        $type = '.';
                        $matchLength = 1;
                        break;
                    }
                    // Fall through to numerical case (could be a decimal dot)

                    // no break
                case '0':
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '8':
                case '9':
                case '+':
                case '-':
                    // Try to find a number
                    if (preg_match($this->number, $input, $match)) {
                        $type = 'literal';
                        $value = '"'.$match[0].'"^^http://www.w3.org/2001/XMLSchema#'.(isset($match[1]) ? 'double' : (preg_match("/^[+\-]?\d+$/", $match[0]) ? 'integer' : 'decimal'));
                    }
                    break;
                case 'B':
                case 'b':
                case 'p':
                case 'P':
                case 'G':
                case 'g':
                    // Try to find a SPARQL-style keyword
                    if (preg_match($this->sparqlKeyword, $input, $match)) {
                        $type = strtoupper($match[0]);
                    } else {
                        $inconclusive = true;
                    }
                    break;

                case 'f':
                case 't':
                    // Try to match a boolean
                    if (preg_match($this->boolean, $input, $match)) {
                        $type = 'literal';
                        $value = '"'.$match[0].'"^^http://www.w3.org/2001/XMLSchema#boolean';
                    } else {
                        $inconclusive = true;
                    }
                    break;

                case 'a':
                    // Try to find an abbreviated predicate
                    if (preg_match($this->shortPredicates, $input, $match)) {
                        $type = 'abbreviation';
                        $value = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
                    } else {
                        $inconclusive = true;
                    }
                    break;
                case '=':
                    // Try to find an implication arrow or equals sign
                    if ($this->n3Mode && \strlen($input) > 1) {
                        $type = 'abbreviation';
                        if ('>' !== $input[1]) {
                            $matchLength = 1;
                            $value = 'http://www.w3.org/2002/07/owl#sameAs';
                        } else {
                            $matchLength = 2;
                            $value = 'http://www.w3.org/2000/10/swap/log#implies';
                        }
                    }
                    break;

                case '!':
                    if (!$this->n3Mode) {
                        break;
                    }
                        // no break
                case ',':
                case ';':
                case '[':
                case ']':
                case '(':
                case ')':
                case '{':
                case '}':
                    // The next token is punctuation
                    $matchLength = 1;
                    $type = $firstChar;
                    break;
                default:
                    $inconclusive = true;
            }

            // Some first characters do not allow an immediate decision, so inspect more
            if ($inconclusive) {
                // Try to find a prefix
                if (('@prefix' === $this->prevTokenType || 'PREFIX' === $this->prevTokenType) && preg_match($this->prefix, $input, $match)) {
                    $type = 'prefix';
                    $value = isset($match[1]) ? $match[1] : '';
                }
                // Try to find a prefixed name. Since it can contain (but not end with) a dot,
                // we always need a non-dot character before deciding it is a prefixed name.
                // Therefore, try inserting a space if we're at the end of the input.
                elseif (preg_match($this->prefixed, $input, $match) || $inputFinished && (preg_match($this->prefixed, $input.' ', $match))) {
                    $type = 'prefixed';
                    $prefix = isset($match[1]) ? $match[1] : '';
                    $value = $this->unescape($match[2]);
                }
            }

            // A type token is special: it can only be emitted after an IRI or prefixed name is read
            if ('^^' === $this->prevTokenType) {
                switch ($type) {
                    case 'prefixed': $type = 'type'; break;
                    case 'IRI':      $type = 'typeIRI'; break;
                    default:         $type = '';
                }
            }

            // What if nothing of the above was found?
            if (!$type) {
                // We could be in streaming mode, and then we just wait for more input to arrive.
                // Otherwise, a syntax error has occurred in the input.
                // One exception: error on an unaccounted linebreak (= not inside a triple-quoted literal).
                if ($inputFinished || (!preg_match('/^\'\'\'|^"""/', $input) && preg_match('/\\n|\\r/', $input))) {
                    return $reportSyntaxError($this);
                } else {
                    $this->input = $input;

                    return $input;
                }
            }
            // Emit the parsed token
            $callback(null, ['line' => $line, 'type' => $type, 'value' => $value, 'prefix' => $prefix]);
            $this->prevTokenType = $type;

            // Advance to next part to tokenize
            $input = substr($input, $matchLength > 0 ? $matchLength : \strlen($match[0]), \strlen($input));
        }
    }

    // ### `_unescape` replaces N3 escape codes by their corresponding characters
    private function unescape($item)
    {
        return preg_replace_callback($this->escapeSequence, function ($match) {
            // $match[0] contains sequence
            $unicode4 = isset($match[1]) ? $match[1] : null;
            $unicode8 = isset($match[2]) ? $match[2] : null;
            $escapedChar = isset($match[3]) ? $match[3] : null;
            $charCode = null;
            if ($unicode4) {
                $charCode = \intval($unicode4, 16);

                return mb_convert_encoding('&#'.(int) $charCode.';', 'UTF-8', 'HTML-ENTITIES');
            } elseif ($unicode8) {
                $charCode = \intval($unicode8, 16);

                return mb_convert_encoding('&#'.(int) $charCode.';', 'UTF-8', 'HTML-ENTITIES');
            } else {
                if (!isset($this->escapeReplacements[$escapedChar])) {
                    throw new \Exception();
                }

                return $this->escapeReplacements[$escapedChar];
            }
        }, $item);
    }

    // ### `_syntaxError` creates a syntax error for the given issue
    private function syntaxError($issue, $line = 0)
    {
        $this->input = null;

        return new \Exception('Unexpected "'.$issue.'" on line '.$line.'.');
    }

    // When handling tokenize as a variable, we can hotswap its functionality when dealing with various serializations
    private function initTokenize()
    {
        $this->_tokenize = function ($input, $finalize) {
            // If the input is a string, continuously emit tokens through the callback until the end
            if (!isset($this->input)) {
                $this->input = '';
            }
            $this->input .= $input;
            $tokens = [];
            $error = '';
            $this->input = $this->tokenizeToEnd(function ($e, $t) use (&$tokens, &$error) {
                if (isset($e)) {
                    $error = $e;
                }
                $tokens[] = $t;
            }, $finalize);
            if ($error) {
                throw $error;
            }

            return $tokens;
        };
    }

    // ## Public methods

    // ### `tokenize` starts the transformation of an N3 document into an array of tokens.
    // The input can be a string or a stream.
    public function tokenize($input, $finalize = true)
    {
        try {
            return \call_user_func($this->_tokenize, $input, $finalize);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // Adds the data chunk to the buffer and parses as far as possible
    public function tokenizeChunk($input)
    {
        return $this->tokenize($input, false);
    }

    public function end()
    {
        // Parses the rest
        return $this->tokenizeToEnd(true, null);
    }
}
