<?php declare(strict_types=1);

namespace Pdsinterop\Rdf\Flysystem\Plugin;

use EasyRdf\Exception as RdfException;
use EasyRdf\Graph as Graph;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Plugin\AbstractPlugin;
use Pdsinterop\Rdf\Enum\Format;
use Pdsinterop\Rdf\Flysystem\Exception;

class ReadRdf extends AbstractPlugin
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\
    private const ERROR_COULD_NOT_CONVERT = 'Could not convert file "%s" to format "%s": %s';

    /** @var Graph */
    private $converter;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return 'readRdf';
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * GetAsRdf constructor.
     *
     * @param Graph $rdfConverter
     */
    final public function __construct(Graph $rdfConverter)
    {
        $this->converter = $rdfConverter;
    }

    /**
     * Get the given file in a given RDF format.
     *
     * @param string $path path to file
     * @param string $format RDF format to convert file to
     * @param string $url base url for parsing
     *
     * @return string|false converted contents
     *
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function handle(string $path, string $format, string $url)
    {
        $converter = $this->converter;

        $filesystem = $this->filesystem;

        $contents = $filesystem->read($path);

        if (is_string($contents)) {
            try {
                $converter->parse($contents, Format::UNKNOWN, $url);
            } catch (RdfException $exception) {
                throw Exception::create(self::ERROR_COULD_NOT_CONVERT, [
                    'file' => $path,
                    'format' => $format,
                    'error' => $exception->getMessage(),
                ], $exception);
            }

            $output = $converter->serialise($format);

            if (!is_scalar($output)) {
                $output = var_export($output, true);
            }

            $contents = $output;
        }

        return $contents;
    }
}
