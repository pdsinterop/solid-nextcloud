<?php declare(strict_types=1);

namespace Pdsinterop\Rdf\Flysystem\Adapter;

use EasyRdf\Exception as RdfException;
use EasyRdf\Graph as Graph;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use ML\JsonLD\JsonLD;
use Pdsinterop\Rdf\Enum\Format;
use Pdsinterop\Rdf\Flysystem\Exception;
use Pdsinterop\Rdf\FormatsInterface;

/**
 * Filesystem adapter to convert RDF files to and from a default format
 */
class Rdf implements RdfAdapterInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public const ERROR_UNSUPPORTED_FORMAT = 'Given format "%s" is not supported';
    public const ERROR_COULD_NOT_CONVERT = 'Could not convert file "%s" to format "%s": %s';

    /** @var AdapterInterface */
    private $adapter;
    /** @var string */
    private $format = '';
    /** @var FormatsInterface */
    private $formats;
    /** @var Graph */
    private $graph;
    /** @var string */
    private $url;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * Retrieve a new / clean RDF Graph object
     *
     * @return Graph
     */
    private function getGraph(): Graph
    {
        return clone $this->graph;
    }

    final public function setFormat(string $format): void
    {
        if (($format !== "") && (Format::has($format) === false)) {
            throw Exception::create(self::ERROR_UNSUPPORTED_FORMAT, [$format]);
        }

        $this->format = $format;
    }

    final public function getFormat(): string
    {
		return $this->format;
	}

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    // @FIXME: Add JsonLD as dependency and use static calls to object instance instead of using static calls to class
    final public function __construct(AdapterInterface $adapter, Graph $graph, FormatsInterface $formats, string $url)
    {
        $this->adapter = $adapter;
        $this->formats = $formats;
        $this->graph = $graph;
        $this->url = $url;
    }

    final public function write($path, $contents, Config $config)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function writeStream($path, $resource, Config $config)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function update($path, $contents, Config $config)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function updateStream($path, $resource, Config $config)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function rename($path, $newpath)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function copy($path, $newpath)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function delete($path)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function deleteDir($dirname)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function createDir($dirname, Config $config)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function setVisibility($path, $visibility)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function has($path)
    {
        $metadata = call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());

        if ($this->format !== '' || $metadata === false) {
            $metadata = $this->getMetadata($path);
        }

        return $metadata;
    }

    final public function read($path)
    {
        $format = $this->format;

        if ($format !== '') {
            $contents = $this->convertedContents($path, $format);

            $metaData = [
                'contents' => $contents,
                'mimetype' => $this->formats->getMimeForFormat($format),
                'path' => $path,
                'size' => strlen($contents), // filesize in bytes,
                'type' => 'file',
            ];
            $metaData = array_merge($metaData, $this->findAuxiliaryResources($path));
        } else {
            $metaData = $this->adapter->read($path);
        }

        return $metaData;
    }

    final public function readStream($path)
    {
        // @TODO: Change to stream?
        return $this->read($path);
    }

    final public function listContents($directory = '', $recursive = false)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

/*/
    @TODO: Add metadata for:

    - [ ] rdf:type - A class whose URI is the expansion of the URI Template [RFC6570] http://www.w3.org/ns/iana/media-types/{+iana-media-type}#Resource,
    - [ ] stat:size - A non-negative integer giving the size of the resource in bytes.
    - [ ] dcterms:modified - The date and time when the resource was last modified.
	  The Last-Modified header value of a resource should retrun the same value
    - [ ] stat:mtime - The Unix time when the resource was last modified.
    - [ ] '<http://www.w3.org/ns/ldp#BasicContainer>; rel="type"'

    Should that be added here or in a separate Solid Metadata adapter?
/*/
    final public function getMetadata($path)
    {
        $metadata = [];

        if ($this->adapter->has($path)) {
            $metadata = $this->adapter->getMetadata($path) ?? [];
	    $format = $this->format;

            if ($format !== '') {
                // @CHECKME: Does it make more sense to call `guessMimeType` or should `getMimeType` be called?
                $metadata = array_merge($metadata, ['mimetype' => $this->guessMimeType($path)], $this->read($path));
            }
            return array_merge($metadata);
        } else {
            return $metadata;
        }
    }

    final public function getSize($path)
    {
        $format = $this->format;

        if ($format === '') {
            $metadata = call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
        } else {
            $metadata = $this->getMetadata($path);
        }

        return $metadata;
    }

    final public function getMimeType($path)
    {
        $format = $this->resetFormat();

        if ($format !== '') {
            $metadata = ['mimetype' => $this->formats->getMimeForFormat($format)];
        } else {
            $metadata = [];

            if ($this->adapter->has($path)) {
                $metadata = $this->adapter->getMimetype($path);
            }

            $possibleMimeType = $this->guessMimeType($path, $metadata);

            if ($possibleMimeType !== '') {
                $metadata['mimetype'] = $possibleMimeType;
            }
        }

        return $metadata;
    }

    final public function getTimestamp($path)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    final public function getVisibility($path)
    {
        return call_user_func_array([$this->adapter, __FUNCTION__], func_get_args());
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function convertedContents($path, $format)
    {
        $originalExtension = $this->getExtension($path);
        $originalContents = $this->getOriginalContents($path);
        $originalFormat = $this->formats->getFormatForExtension($originalExtension);

		if ($originalFormat === $format) {
			return $originalContents;
		}

		try {
			switch($originalFormat) {
				case "jsonld":
                    $graph = $this->getGraph();
					// FIXME: parsing json gives warnings, so we're suppressing those for now.
					$graph->parse($originalContents, "jsonld", $this->url);
					switch ($format) {
						default:
							$contents = $graph->serialise($format);
							// FIXME: we should not remove the xsd namespace, but couldn't find a way yet to prevent the serialiser from adding them. xsd namespace;
							$contents = preg_replace("/\^\^xsd:string /", "", $contents);
							$contents = str_replace("@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n", "", $contents);
						break;
					}
				break;
				default:
                    $graph = $this->getGraph();
                    // FIXME: guessing here helps pass another test, but we really should provide a correct format.
					// FIXME: parsing json gives warnings, so we're suppressing those for now.
					@$graph->parse($originalContents, "guess", $this->url);
					switch ($format) {
						case "jsonld":
							// We need to get the expanded version of the json-ld, but easyRdf doesn't provide an option for that, so we call this directly.
							$contents = $graph->serialise($format);
							$jsonDoc = JsonLD::expand($contents);
							$contents = JsonLD::toString($jsonDoc);
						break;
						default:
							$contents = $graph->serialise($format);
						break;
					}
				break;
			}
		} catch (RdfException $exception) {
            throw Exception::create(self::ERROR_COULD_NOT_CONVERT, [
				'file' => $path,
				'format' => $format,
				'error' => $exception->getMessage(),
			], $exception);
		}

        return $contents;
    }

    private function findAuxiliaryResources(string $path): array
    {
        $metaFiles = [
            'describedby' => $this->findInPath($path, '.meta'),
            'acl' => $this->findInPath($path, '.acl'),
        ];

        // Remove any empty values
        return array_filter($metaFiles);
    }

    private function findInPath(string $originalPath, $extension)
    {
        $subject = false;

        $subjectPath = $originalPath . $extension;

        if ($this->adapter->has($subjectPath)) {
            $subject = $subjectPath;
        } else {
            do {
                $subjectPath = dirname($subjectPath);

                if ($subjectPath === '.' || $subjectPath === '/') {
                    // We have reached the root of the file system
                    if ($this->adapter->has($extension)) {
                        $subject = $extension;
                    }
                    break;
                } else {
                    $path = $subjectPath . '/' . $extension;

                    if ($this->adapter->has($path)) {
                        $subject = $path;
                    }
                }

            } while ($subject === false);
        }

        return $subject;
    }

    private function getExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private function getOriginalContents($path)
    {
        $converted = $this->adapter->read($path);

        return $converted['contents'];
    }

    private function guessMimeType(string $path, array $metadata = []): string
    {
        $mimetype = '';

        if ($metadata === []) {
            $originalMetadata = [];
            if ($this->adapter->has($path)) {
                $originalMetadata = $this->adapter->getMimetype($path);
            }

            if (isset($originalMetadata['mimetype'])) {
                $metadata = $originalMetadata;
            }
        }

        $extension = $this->getExtension($path);

        $possibleMime = $this->formats->getMimeForExtension($extension);

        if ($possibleMime !== ''
            && (
                ! isset($metadata['mimetype'])
                || $metadata['mimetype'] === 'text/plain'
            )
        ) {
            $mimetype = $possibleMime;
        }

        return $mimetype;
    }

    private function resetFormat() : string
    {
        $format = $this->format;

        $this->format = '';

        return $format;
    }
}
