<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/**
 * TrigParser wrapper turning it into a triple/quad generator.
 *
 * Parses the input in chunks and reads the triples in a lazy way which assures
 * both speed and low memory footprint.
 *
 * Can be reused (meaning parse() and parseStream() methods can be run
 * multiple times).
 *
 * Use as follows:
 *
 * ```
 * $parser = new TrigParserIterator();
 * foreach ($parser as $quad) {
 *     ...do something...
 * }
 * ```
 */
class TriGParserIterator implements \Iterator
{
    /**
     * Store TriG
     *
     * @var array
     */
    private $options;
    private $prefixCallback;
    /**
     * @var \pietercolpaert\hardf\TriGParser
     */
    private $parser;
    private $chunkSize;
    private $input;
    private $triplesBuffer;
    private $n;
    private $tmpStream;

    /**
     * Creates a parser object. For documentation of parameters, see the
     * \pietercolpaert\hardf\TrigParser constructor documentation.
     *
     * If you're using this class, you probably don't need the $tripleCallback
     * but $prefixCallback can be still useful.
     *
     * @param array    $options
     * @param callable $prefixCallback
     */
    public function __construct($options = [], $prefixCallback = null)
    {
        $this->options = $options;
        $this->prefixCallback = $prefixCallback;
    }

    public function __destruct()
    {
        $this->closeTmpStream();
    }

    /**
     * A thiny wrapper for the parseStream() method turning a string into
     * a stream resource.
     */
    public function parse(string $input): \Iterator
    {
        $this->closeTmpStream();
        $this->tmpStream = fopen('php://memory', 'r+');
        fwrite($this->tmpStream, $input);
        rewind($this->tmpStream);

        return $this->parseStream($this->tmpStream);
    }

    /**
     * Parses a given input stream using a given chunk size.
     *
     * @param resource $input
     *
     * @throws \Exception
     */
    public function parseStream($input, int $chunkSize = 8192): \Iterator
    {
        if (!\is_resource($input)) {
            throw new \Exception('Input has to be a resource');
        }

        $this->input = $input;
        $this->chunkSize = $chunkSize;
        $this->n = -1;
        $this->triplesBuffer = [];
        $this->parser = new TriGParser($this->options, null, $this->prefixCallback);

        return $this;
    }

    public function current()
    {
        return current($this->triplesBuffer);
    }

    public function key()
    {
        return $this->n;
    }

    public function next(): void
    {
        $el = next($this->triplesBuffer);
        if (false === $el) {
            $this->triplesBuffer = [];
            $this->parser->setTripleCallback(function (?\Exception $e,
                                                      ?array $quad): void {
                if ($e) {
                    throw $e;
                }
                if ($quad) {
                    $this->triplesBuffer[] = $quad;
                }
            });
            while (!feof($this->input) && 0 === \count($this->triplesBuffer)) {
                $this->parser->parseChunk(fgets($this->input, $this->chunkSize));
            }
            if (feof($this->input)) {
                $this->parser->end();
            }
        }
        ++$this->n;
    }

    /**
     * @throws \Exception
     */
    public function rewind(): void
    {
        $ret = rewind($this->input);
        if (true !== $ret) {
            throw new \Exception("Can't seek in the input stream");
        }
        $this->next();
    }

    public function valid(): bool
    {
        return false !== current($this->triplesBuffer);
    }

    private function closeTmpStream(): void
    {
        if (\is_resource($this->tmpStream)) {
            fclose($this->tmpStream);
            $this->tmpStream = null;
        }
    }
}
