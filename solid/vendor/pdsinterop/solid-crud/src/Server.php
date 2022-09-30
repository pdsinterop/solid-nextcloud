<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Resources;

use EasyRdf\Exception as RdfException;
use EasyRdf\Graph as Graph;
use Laminas\Diactoros\ServerRequest;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface as Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use WebSocket\Client;
use pietercolpaert\hardf\TriGWriter;
use pietercolpaert\hardf\TriGParser;

class Server
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public const ERROR_CAN_NOT_PARSE_FOR_PATCH = 'Could not parse the requested resource for patching';
    public const ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER = 'Only empty containers can be deleted, "%s" is not empty';
    public const ERROR_CAN_NOT_PARSE_METADATA = 'Could not parse metadata for %s';
    public const ERROR_CAN_NOT_REDIRECT_WITHOUT_URL = "Cannot create %s: no URL set";
    public const ERROR_MISSING_SPARQL_CONTENT_TYPE = 'Request is missing required Content-Type "application/sparql-update" or "application/sparql-update-single-match"';
    public const ERROR_UNHANDLED_PATCH_CONTENT_TYPE = 'Request uses an unhandled Content-Type';
    public const ERROR_MULTIPLE_LINK_METADATA_FOUND = 'More than one link-metadata found for %s';
    public const ERROR_NOT_IMPLEMENTED_SPARQL = 'SPARQL Not Implemented';
    public const ERROR_PATH_DOES_NOT_EXIST = 'Requested path "%s" does not exist';
    public const ERROR_PATH_EXISTS = 'Requested path "%s" already exists';
    public const ERROR_POST_EXISTING_RESOURCE = 'Requested path "%s" already exists. Can not "POST" to existing resource. Use "PUT" instead';
    public const ERROR_PUT_EXISTING_RESOURCE = self::ERROR_PATH_EXISTS . '. Can not "PUT" existing container.';
    public const ERROR_PUT_NON_EXISTING_RESOURCE = self::ERROR_PATH_DOES_NOT_EXIST . '. Can not "PUT" non-existing resource. Use "POST" instead';
    public const ERROR_UNKNOWN_HTTP_METHOD = 'Unknown or unsupported HTTP METHOD "%s"';

    private const MIME_TYPE_DIRECTORY = 'directory';
    private const QUERY_PARAM_HTTP_METHOD = 'http-method';

    /** @var string[] */
    private $availableMethods = [
        'DELETE',
        'GET',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'POST',
        'PUT',
    ];
    /** @var string */
    private $basePath;
    /** @var string */
    private $baseUrl;
    /** @var Filesystem */
    private $filesystem;
    /** @var Graph */
    private $graph;
    /** @var string */
    private $pubsub;
    /** @var Response */
    private $response;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getFilesystem()
    {
        return $this->filesystem;
    }

    final public function getResponse()
    {
        return $this->response;
    }

    private function getGraph(): Graph
    {
        return clone $this->graph;
    }

    final public function setBaseUrl($url)
    {
        $this->baseUrl = $url;

        $serverRequest = new ServerRequest(array(),array(), $this->baseUrl);
        $this->basePath = $serverRequest->getUri()->getPath();
    }

    final public function setPubSubUrl($url)
    {
        $this->pubsub = $url;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    // @TODO: The Graph should be injected by the caller
    final public function __construct(Filesystem $filesystem, Response $response, Graph $graph = null)
    {
        $this->basePath = '';
        $this->baseUrl = '';
        $this->pubsub = '';
        $this->filesystem = $filesystem;
        $this->graph = $graph ?? new Graph();
        $this->response = $response;
        // @TODO: Mention \EasyRdf_Namespace::set('lm', 'https://purl.org/pdsinterop/link-metadata#');
    }

    final public function respondToRequest(Request $request): Response
    {
        $path = $request->getUri()->getPath();
        if ($this->basePath) {
            $path = str_replace($this->basePath, "", $path);
        }
        $path = rawurldecode($path);

        // The path can also come from a 'Slug' header
        if ($path === '' && $request->hasHeader('Slug')) {
            $slugs = $request->getHeader('Slug');
            // @CHECKME: First set header wins, is this correct? Or should it be the last one?
            $path = reset($slugs);
        }

        $method = $this->getRequestMethod($request);

        $contents = $request->getBody()->getContents();

        return $this->handle($method, $path, $contents, $request);
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function getRequestMethod(Request $request): string
    {
        $method = $request->getMethod();

        $queryParams = $request->getQueryParams();

        if (
            array_key_exists(self::QUERY_PARAM_HTTP_METHOD, $queryParams)
            && in_array(strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]), $this->availableMethods, true)
        ) {
            $method = strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]);
        }

        return $method;
    }

    private function handle(string $method, string $path, $contents, $request): Response
    {
        $response = $this->response;
        $filesystem = $this->filesystem;

        // Lets assume the worst...
        $response = $response->withStatus(500);

        // Set Accept, Allow, and CORS headers
        // $response = $response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Credentials','true')
            // ->withHeader('Access-Control-Allow-Headers', '*, authorization, accept, content-type')
            // @FIXME: Add correct headers to resources (for instance allow DELETE on a GET resource)
            // ->withAddedHeader('Accept-Patch', 'text/ldpatch')
            // ->withAddedHeader('Accept-Post', 'text/turtle, application/ld+json, image/bmp, image/jpeg')
            // ->withHeader('Allow', 'GET, HEAD, OPTIONS, PATCH, POST, PUT')
        //;

        switch ($method) {
            case 'DELETE':
                $response = $this->handleDeleteRequest($response, $path, $contents);
            break;
            case 'GET':
            case 'HEAD':
                $mime = $this->getRequestedMimeType($request->getHeaderLine("Accept"));
                $response = $this->handleReadRequest($response, $path, $contents, $mime);
                if ($method === 'HEAD') {
                    $response->getBody()->rewind();
                    $response->getBody()->write('');
                    $response = $response->withStatus(204); // CHECKME: nextcloud will remove the updates-via header - any objections to give the 'HEAD' request a 'no content' response type?
                    if ($this->pubsub) {
                        $response = $response->withHeader("updates-via", $this->pubsub);
                    }
                }
                break;

            case 'OPTIONS':
                $response = $response
                    ->withHeader('Vary', 'Accept')
                    ->withStatus(204)
                ;
                break;

            case 'PATCH':
                $contentType= $request->getHeaderLine("Content-Type");
                switch($contentType) {
                    case "application/sparql-update":
                    case "application/sparql-update-single-match":
                        $response = $this->handleSparqlUpdate($response, $path, $contents);
                    break;
                    case "text/n3":
                        $response = $this->handleN3Update($response, $path, $contents);
                    break;
                    default:
                        $response->getBody()->write(self::ERROR_UNHANDLED_PATCH_CONTENT_TYPE);
                        $response = $response->withStatus(400);
                    break;
                }
            break;
            case 'POST':
                $pathExists = $filesystem->has($path);
                if ($pathExists) {
                    $mimetype = $filesystem->getMimetype($path);
                }
                if ($path === "/") {
                    $pathExists = true;
                    $mimetype = self::MIME_TYPE_DIRECTORY;
                }
                if ($pathExists === true) {
                    if (isset($mimetype) && $mimetype === self::MIME_TYPE_DIRECTORY) {
                        $contentType= explode(";", $request->getHeaderLine("Content-Type"))[0];
                        $slug = $request->getHeaderLine("Slug");
                        if ($slug) {
                            $filename = $slug;
                        } else {
                            $filename = $this->guid();
                        }
                        // FIXME: make this list complete for at least the things we'd expect (turtle, n3, jsonld, ntriples, rdf);
                        switch ($contentType) {
                            case '':
                                // FIXME: if no content type was passed, we should reject the request according to the spec;
                            break;
                            case "text/plain":
                                $filename .= ".txt";
                            break;
                            case "text/turtle":
                                $filename .= ".ttl";
                            break;
                            case "text/html":
                                $filename .= ".html";
                            break;
                            case "application/json":
                            case "application/ld+json":
                                $filename .= ".json";
                            break;
                        }

                        $response = $this->handleCreateRequest($response, $path . $filename, $contents);
                    } else {
                        $response = $this->handleUpdateRequest($response, $path, $contents);
                    }
                } else {
                    $response = $this->handleCreateRequest($response, $path, $contents);
                }
            break;
            case 'PUT':
                $link = $request->getHeaderLine("Link");
                switch ($link) {
                    case '<http://www.w3.org/ns/ldp#BasicContainer>; rel="type"':
                        $response = $this->handleCreateDirectoryRequest($response, $path);
                    break;
                    default:
                        if ($filesystem->has($path) === true) {
                            $response = $this->handleUpdateRequest($response, $path, $contents);
                        } else {
                            $response = $this->handleCreateRequest($response, $path, $contents);
                        }
                    break;
                }
            break;
            default:
                throw Exception::create(self::ERROR_UNKNOWN_HTTP_METHOD, [$method]);
                break;
        }

        return $response;
    }

    private function handleSparqlUpdate(Response $response, string $path, $contents): Response
    {
        $filesystem = $this->filesystem;
        $graph = $this->getGraph();

        if ($filesystem->has($path) === false) {
            $data = '';
        } else {
            // read ttl data
            $data = $filesystem->read($path);
        }

        try {
            // Assuming this is in our native format, turtle
            // @CHECKME: Does the Graph Parse here also need an URI?
            $graph->parse($data, "turtle");
            // FIXME: Adding this base will allow us to parse <> entries; , $this->baseUrl . $this->basePath . $path), but that breaks the build.
            // FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format instead of 'turtle'?

            // parse query in contents
            if (preg_match_all("/((INSERT|DELETE).*{([^}]*)})+/", $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $command = $match[2];
                    $triples = $match[3];

                    // apply changes to ttl data
                    switch($command) {
                        case "INSERT":
                            // insert $triple(s) into $graph
                            // @CHECKME: Does the Graph Parse here also need an URI?
                            $graph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;

                        break;
                        case "DELETE":
                            // delete $triples from $graph
                            $deleteGraph = $this->getGraph();
                            // @CHECKME: Does the Graph Parse here also need an URI?
                            $deleteGraph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;
                            $resources = $deleteGraph->resources();
                            foreach ($resources as $resource) {
                                $properties = $resource->propertyUris();
                                foreach ($properties as $property) {
                                    $values = $resource->all($property);
                                    if (!count($values)) {
                                        $graph->delete($resource, $property);
                                    } else {
                                        foreach ($values as $value) {
                                            $count = $graph->delete($resource, $property, $value);
                                            if ($count === 0) {
                                                throw new Exception("Could not delete a value", 500);
                                            }
                                        }
                                    }
                                }
                            }
                        break;
                        default:
                            throw new Exception("Unimplemented SPARQL", 500);
                        break;
                    }
                }
            }

            // Assuming this is in our native format, turtle
            $output = $graph->serialise("turtle"); // FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format?
            // write ttl data

            if ($filesystem->has($path) === true) {
                $success = $filesystem->update($path, $output);
            } else {
                $success = $filesystem->write($path, $output);
            }

            $response = $response->withStatus($success ? 201 : 500);

            if ($success) {
                $this->removeLinkFromMetaFileFor($path);
                $this->sendWebsocketUpdate($path);
            }
        } catch (RdfException $exception) {
            $response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
            $response = $response->withStatus(501);
        } catch (Throwable $exception) {
            $response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
            $response = $response->withStatus(501);
        }

        return $response;
    }

    private function normalizeN3($contents) {
        $parser = new TriGParser(["format" => "n3"]);
        $triples = $parser->parse($contents);
        $parsedGraph = [];
        foreach ($triples as $key => $value) {
            $graph = $value['graph'];
            $subject = $value['subject'];
            $predicate = $value['predicate'];
            $object = $value['object'];

            if ($graph == '') {
                $graph = ':root';
            }
            if ($graph != ':root') {
                $value['graph'] = '';
                $parsedGraph[$graph] = $value;
            } else {
                if (!isset($parsedGraph[$graph])) {
                    $parsedGraph[$graph] = [];
                }
                if (!isset($parsedGraph[$graph][$subject])) {
                    $parsedGraph[$graph][$subject] = [];
                }
                if (!isset($parsedGraph[$graph][$subject][$predicate])) {
                    $parsedGraph[$graph][$subject][$predicate] = [];
                }
                $parsedGraph[$graph][$subject][$predicate][] = $object;
            }
        }
        return $parsedGraph;
    }

    private function n3Convert($contents) {
        $parsedGraph = $this->normalizeN3($contents);
        $result = array();
        foreach ($parsedGraph[':root'] as $subject) {
            if (in_array('http://www.w3.org/ns/solid/terms#InsertDeletePatch', $subject['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
                foreach ($subject as $predicate => $value) {
                    switch ($predicate) {
                        case 'http://www.w3.org/ns/solid/terms#inserts':
                            foreach ($value as $target) {
                                if (!isset($result['insert'])) {
                                    $result['insert'] = array();
                                }
                                $result['insert'][] = $parsedGraph[$target];
                            }
                        break;
                        case 'http://www.w3.org/ns/solid/terms#deletes':
                            foreach ($value as $target) {
                                if (!isset($result['delete'])) {
                                    $result['delete'] = array();
                                }
                                $result['delete'][] = $parsedGraph[$target];
                            }
                        break;
                    }
                }
            }
        }

        foreach ($result as $key => $value) {
            $writer = new TriGWriter(["format" => "turtle"]);
            $writer->addTriples($value);
            $result[$key] = $writer->end();
        }
        return $result;
    }

    private function handleN3Update(Response $response, string $path, $contents): Response
    {
        $filesystem = $this->filesystem;
        $graph = $this->getGraph();
        $n3Graph = $this->getGraph();

        if ($filesystem->has($path) === false) {
            $data = '';
        } else {
            // read ttl data
            $data = $filesystem->read($path);
        }

        try {
            // Assuming this is in our native format, turtle
            // @CHECKME: Does the Graph Parse here also need an URI?
            $graph->parse($data, "turtle");
            // FIXME: Adding this base will allow us to parse <> entries; , $this->baseUrl . $this->basePath . $path), but that breaks the build.
            // FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format instead of 'turtle'?
            $instructions = $this->n3Convert($contents);
            foreach ($instructions as $key => $value) {
                switch ($key) {
                    case "insert":
                        // error_log("INSERT");
                        // error_log($instructions['insert']);
                        $graph->parse($instructions['insert'], "turtle");
                    break;
                    case "delete":
                        $deleteGraph = $this->getGraph();
                        // error_log("DELETE");
                        // error_log($instructions['delete']);

                        // @CHECKME: Does the Graph Parse here also need an URI?
                        $deleteGraph->parse($instructions['delete'], "turtle");
                        $resources = $deleteGraph->resources();
                        foreach ($resources as $resource) {
                            $properties = $resource->propertyUris();
                            foreach ($properties as $property) {
                                $values = $resource->all($property);
                                if (!count($values)) {
                                    $graph->delete($resource, $property);
                                } else {
                                    foreach ($values as $value) {
                                        $count = $graph->delete($resource, $property, $value);
                                        if ($count === 0) {
                                            throw new Exception("Could not delete a value", 500);
                                        }
                                    }
                                }
                            }
                            // FIXME: Is there a 'patches'? What does it look like and how do we handle it?
                        }
                    break;
                }
            }

            // Assuming this is in our native format, turtle
            $output = $graph->serialise("turtle"); // FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format?
            // write ttl data

            if ($filesystem->has($path) === true) {
                $success = $filesystem->update($path, $output);
            } else {
                $success = $filesystem->write($path, $output);
            }

            $response = $response->withStatus($success ? 201 : 500);

            if ($success) {
                $this->removeLinkFromMetaFileFor($path);
                $this->sendWebsocketUpdate($path);
            }
        } catch (RdfException $exception) {
            $response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
            $response = $response->withStatus(501);
        } catch (Throwable $exception) {
            $response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
            $response = $response->withStatus(501);
        }

        return $response;
    }


    private function handleCreateRequest(Response $response, string $path, $contents): Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = false;

            set_error_handler(static function ($severity, $message, $filename, $line) {
                throw new \ErrorException($message, 0, $severity, $filename, $line);
            });

            try {
                $success = $filesystem->write($path, $contents);
            } catch (FileExistsException $e) {
                $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
                $response->getBody()->write($message);

                return $response->withStatus(400);
            } catch (Throwable $exception) {
                /*/ An error occurred in the underlying flysystem adapter /*/
                $message = vsprintf('Could not write to path %s: %s', [$path, $exception->getMessage()]);
                $response->getBody()->write($message);

                return $response->withStatus(400);
            } finally {
                restore_error_handler();
            }

            if ($success) {
                $this->removeLinkFromMetaFileFor($path);
                $response = $response->withHeader("Location", $this->baseUrl . $path);
                $response = $response->withStatus(201);
                $this->sendWebsocketUpdate($path);
            } else {
                $response = $response->withStatus(500);
            }
        }

        return $response;
    }

    private function parentPath($path)
    {
        if ($path === "/") {
            return "/";
        }
        $pathicles = explode("/", $path);
        $end = array_pop($pathicles);
        if ($end === "") {
            array_pop($pathicles);
        }
        return implode("/", $pathicles) . "/";
    }

    private function handleCreateDirectoryRequest(Response $response, string $path): Response
    {
        $filesystem = $this->filesystem;
        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = $filesystem->createDir($path);
            $response = $response->withStatus($success ? 201 : 500);
            if ($success) {
                $this->removeLinkFromMetaFileFor($path);
                $this->sendWebsocketUpdate($path);
            }
        }

        return $response;
    }

    private function sendWebsocketUpdate($path)
    {
        $pubsub = $this->pubsub;
        if (!$pubsub) {
            return; // no pubsub server available, don't even try;
        }

        $pubsub = str_replace(["https://", "http://"], "ws://", $pubsub);

        $baseUrl = $this->baseUrl;

        $client = new Client($pubsub, array(
            'headers' => array(
                'Sec-WebSocket-Protocol' => 'solid-0.1'
            )
        ));

        try {
            $client->send("pub $baseUrl$path\n");

            while ($path !== "/") {
                $path = $this->parentPath($path);
                $client->send("pub $baseUrl$path\n");
            }
        } catch (\WebSocket\Exception $exception) {
            throw new Exception('Could not write to pub-sup server', 502, $exception);
        }
    }

    private function handleDeleteRequest(Response $response, string $path, $contents): Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path)) {
            $mimetype = $filesystem->getMimetype($path);

            if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                $directoryContents = $filesystem->listContents($path, true);
                if (count($directoryContents) > 0) {
                    $status = 400;
                    $message = vsprintf(self::ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER, [$path]);
                    $response->getBody()->write($message);
                } else {
                    $success = $filesystem->deleteDir($path);
                    if ($success) {
                        $this->sendWebsocketUpdate($path);
                    }

                    $status = $success ? 204 : 500;
                }
            } else {
                $success = $filesystem->delete($path);
                if ($success) {
                    $this->sendWebsocketUpdate($path);
                }
                $status = $success ? 204 : 500;
            }

            $response = $response->withStatus($status);
        } else {
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        }

        return $response;
    }

    private function handleUpdateRequest(Response $response, string $path, string $contents): Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === false) {
            $message = vsprintf(self::ERROR_PUT_NON_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = $filesystem->update($path, $contents);
            $response = $response->withStatus($success ? 201 : 500);
            if ($success) {
                $this->removeLinkFromMetaFileFor($path);
                $this->sendWebsocketUpdate($path);
            }
        }

        return $response;
    }

    private function getRequestedMimeType($accept)
    {
        // text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8
        $mimes = explode(",", $accept);
        foreach ($mimes as $mime) {
                        $parts = explode(";", $mime);
                        $mimeInfo = $parts[0];
            switch ($mimeInfo) {
                case "text/turtle": // turtle
                case "application/ld+json": //json
                case "application/rdf+xml": //rdf
                    return $mimeInfo;
                break;
            }
        }
        return '';
    }

    private function handleReadRequest(Response $response, string $path, $contents, $mime=''): Response
    {
        $filesystem = $this->filesystem;
        if ($path === "/") { // FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
            $contents = $this->listDirectoryAsTurtle($path);
            $response->getBody()->write($contents);
            $response = $response->withHeader("Content-type", "text/turtle");
            $response = $response->withStatus(200);
                } elseif(($filesystem->has($path) === false) && (($path == ".meta") || ($path == "/.meta"))) {
            $contents = '';
            $response->getBody()->write($contents);
            $response = $response->withHeader("Content-type", "text/turtle");
            $response = $response->withStatus(200);
        } elseif ($filesystem->has($path) === false && $this->hasDescribedBy($path) === false) {
            /*/ The file does not exist and no link-metadata is present /*/
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        } else {
            $linkMetadataResponse = $this->handleLinkMetadata($response, $path);
            if ($linkMetadataResponse !== null) {
                /*/ Link-metadata is present, return the altered response /*/
                $response = $linkMetadataResponse;
            } elseif ($filesystem->getMimetype($path) === self::MIME_TYPE_DIRECTORY) {
                $contents = $this->listDirectoryAsTurtle($path);
                $response->getBody()->write($contents);
                $response = $response->withHeader("Content-type", "text/turtle")->withStatus(200);
            } elseif ($filesystem->asMime($mime)->has($path)) {
            /*/ The file does exist and no link-metadata is present /*/
                $response = $this->addLinkRelationHeaders($response, $path, $mime);

                if (preg_match('/\.(acl|meta|ttl)$/', $path)) {
                    $mimetype = "text/turtle"; // FIXME: teach  flysystem that .acl/.meta/.ttl means text/turtle
                } else {
                    $mimetype = $filesystem->asMime($mime)->getMimetype($path);
                }

                $contents = $filesystem->asMime($mime)->read($path);

                if ($contents !== false) {
                    $response->getBody()->write($contents);
                    $response = $response->withHeader("Content-type", $mimetype)->withStatus(200);
                } else {
                    // FIXME: we should not get here if the file does not exist, but here we are. It looks like $filesystem->has("/.meta") always returns true even if the file does not exist;
                    if ($path == "/.meta") {
            $contents = '';
            $response->getBody()->write($contents);
            $response = $response->withHeader("Content-type", "text/turtle");
            $response = $response->withStatus(200);
                    } else {
                        /*/ The file does exist in another format and no link-metadata is present /*/
                        $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
                        $response->getBody()->write($message);
                        $response = $response->withStatus(404);
                    }
                }
            } else {
                /*/ The file does exist in another format and no link-metadata is present /*/
                $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
                $response->getBody()->write($message);
                $response = $response->withStatus(404);
            }
        }

        return $response;
    }

    private function guid()
    {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function listDirectoryAsTurtle($path)
    {
        $filesystem = $this->filesystem;
        if ($path === "/") {
            $listContents = $filesystem->listContents(".");// FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
        } else {
            $listContents = $filesystem->listContents($path);
        }
        // CHECKME: maybe structure this data als RDF/PHP
        // https://www.easyrdf.org/docs/rdf-formats-php

        // @FIXME: The $name variable is declared here but never used. Should it be removed or is there a bug further down?
        $name = basename($path) . ":";
        // turtle syntax doesn't allow labels that start with a number, so prefix it if it does;
        if (preg_match("/^\d/", $name)) {
            $name = "container-" . $name;
        }

        $turtle = array(
            "<>" => array(
                "a" => array("ldp:BasicContainer", "ldp:Container", "ldp:Resource"),
                "ldp:contains" => array()
            )
        );

        foreach ($listContents as $item) {
            switch($item['type']) {
                case "file":
                    // ACL and meta files should not be listed in directory overview
                    if (
                        $item['basename'] !== '.meta'
                        && in_array($item['extension'], ['acl', 'meta']) === false
                    ) {
                        try {
                            $linkMetadataResponse = $this->handleLinkMetadata(clone $this->response, $item['path']);
                        } catch (Exception $e) {
                            // If the link-metadata can not be retrieved for whatever reason, it should just be listed
                            // The error will surface when the file itself is accessed
                            $linkMetadataResponse = null;
                        }

                        if (
                            $linkMetadataResponse === null
                            || in_array($linkMetadataResponse->getStatusCode(), [404, 410]) === false
                        ) {
                            /*/ Only files without link-metadata instruction, or with a redirect instruction may be shown /*/
                            $filename = "<" . rawurlencode($item['basename']) . ">";
                            $turtle[$filename] = array(
                                "a" => array("ldp:Resource")
                            );
                            $turtle["<>"]['ldp:contains'][] = $filename;
                        }
                    }
                break;
                case "dir":
                    // FIXME: we have a trailing slash here to please the test suits, but it probably should also pass without it since we are a Container.
                    $filename = "<" . rawurlencode($item['basename']) . "/>";
                    $turtle[$filename] = array(
                        "a" => array("ldp:BasicContainer", "ldp:Container", "ldp:Resource")
                    );
                    $turtle["<>"]['ldp:contains'][] = $filename;
                break;
                default:
                    throw new Exception("Unknown type", 500);
                break;
            }
        }

        $container = <<< EOF
@prefix : <#>.
@prefix ldp: <http://www.w3.org/ns/ldp#>.

EOF;

        foreach ($turtle as $name => $item) {
            $container .= "\n$name\n";
            $lines = [];
            foreach ($item as $property => $values) {
                if (count($values)) {
                    $lines[] = "\t" . $property . " " . implode(", ", $values);
                }
            }

            $container .= implode(";\n", $lines);
            $container .= ".\n";
        }

        return $container;
    }

    // =========================================================================
    // @TODO: All Auxiliary Resources logic should probably be moved to a separate class.

    /**
     * Currently, in the spec channel, it is under consideration to use
     * <http://www.w3.org/ns/auth/acl#accessControl> or <http://www.w3.org/ns/solid/terms#acl>
     * instead of (or besides) "acl" and <https://www.w3.org/ns/iana/link-relations/relation#describedby>
     * instead of (or besides) "describedby".
     *
     * @see https://github.com/solid/specification/issues/172
     */
    private function addLinkRelationHeaders(Response $response, string $path, $mime=null): Response
    {
        // @FIXME: If a `.meta` file is requested, it must have header `Link: </path/to/resource>; rel="describes"`

        //@CHECKME: Should the ACL link header be added here or in/by the Auth server?
        if ($this->hasAcl($path, $mime)) {
            $value = sprintf('<%s>; rel="acl"', $this->getAclPath($path, $mime));
            $response = $response->withAddedHeader('Link', $value);
        }

        if ($this->hasDescribedBy($path, $mime)) {
            $value = sprintf('<%s>; rel="describedby"', $this->getDescribedByPath($path, $mime));
            $response = $response->withAddedHeader('Link', $value);
        }

        return $response;
    }

    private function getAclPath(string $path, $mime = null): string
    {
        $metadataCache = $this->getMetadata($path, $mime);

        return $metadataCache[$path]['acl'] ?? '';
    }

    private function getDescribedByPath(string $path, $mime = null): string
    {
        $metadataCache = $this->getMetadata($path, $mime);

        return $metadataCache[$path]['describedby'] ?? '';
    }

    private function getMetadata(string $path, $mime) : array
    {
        // @NOTE: Because the lookup can be expensive, we cache the result
        static $metadataCache = [];

        if (isset($metadataCache[$path]) === false) {
            $filesystem = $this->filesystem;

            try {
                if ($mime) {
                    $metadata = $filesystem->asMime($mime)->getMetadata($path);
                } else {
                    $metadata = $filesystem->getMetadata($path);
                }
            } catch (FileNotFoundException $e) {
                $metadata = [];
            }

            $metadataCache[$path . $mime] = $metadata;
        }

        return $metadataCache;
    }

    private function hasAcl(string $path, $mime = null): bool
    {
        return $this->getAclPath($path, $mime) !== '';
    }

    private function hasDescribedBy(string $path, $mime = null): bool
    {
        return $this->getDescribedByPath($path, $mime) !== '';
    }

    // =========================================================================
    // @TODO: All link-metadata Response logic should probably be moved to a separate class.

    private function handleLinkMetadata(Response $response, string $path)
    {
        $returnResponse = null;

        if ($this->hasDescribedBy($path)) {
            $linkMeta = $this->parseLinkedMetadata($path);

            if (isset($linkMeta['type'], $linkMeta['url'])) {
                $returnResponse = $this->buildLinkMetadataResponse($response, $linkMeta['type'], $linkMeta['url']);
            }
        }

        return $returnResponse;
    }

    private function buildLinkMetadataResponse(Response $response, $type, $url = null)
    {
        switch ($type) {
            case 'deleted':
                $returnResponse = $response->withStatus(404);
            break;

            case 'forget':
                $returnResponse = $response->withStatus(410);
            break;

            case 'redirectPermanent':
                if ($url === null) {
                    throw Exception::create(self::ERROR_CAN_NOT_REDIRECT_WITHOUT_URL, [$type]);
                }
                $returnResponse = $response->withHeader('Location', $url)->withStatus(308);
            break;

            case 'redirectTemporary':
                if ($url === null) {
                    throw Exception::create(self::ERROR_CAN_NOT_REDIRECT_WITHOUT_URL, [$type]);
                }
                $returnResponse = $response->withHeader('Location', $url)->withStatus(307);
            break;

            default:
                // No (known) Link Metadata present = follow regular logic
                $returnResponse = null;
            break;
        }

        return $returnResponse;
    }

    private function parseLinkedMetadata(string $path)
    {
        $linkMeta = [];

        try {
            $describedByPath = $this->filesystem->getMetadata($path)['describedby'] ?? '';
            $describedByContents = $this->filesystem->read($describedByPath);
        } catch (FileNotFoundException $e) {
            // If, for whatever reason, the file is not present after all, the resource should still be returned (or a 404)
            // @CHECKME: Should the upstream add a message to the header or something?
            return $linkMeta;
        }

        $graph = $this->getGraph();

        try {
            $graph->parse($describedByContents, null, '/'.$describedByPath);
        } catch (RdfException $exception) {
            // If the metadata can not be parsed, the resource should still be returned (or a 404)
            // @CHECKME: Should the upstream add a message to the header or something?
            return $linkMeta;
        }

        $toRdfPhp = $graph->toRdfPhp();

        $rdfPaths = array_keys($toRdfPhp);
        $foundPath = $this->findPath($rdfPaths, $path);

        // If the requested path is a sub folder or file, it also needs te be handled
        foreach ($rdfPaths as $rdfPath) {
            if (strpos($rdfPath, $path) !== false) {
                $foundPath = $rdfPath;
                 break;
            }
        }

        if (isset($toRdfPhp[$foundPath])) {
            $filteredRdfData = array_filter($toRdfPhp[$foundPath], static function ($key) {
                $uris = implode('|', [
                    'pdsinterop.org/solid-link-metadata/links.ttl',
                    'purl.org/pdsinterop/link-metadata',
                ]);

                return (bool) preg_match("#({$uris})#",
                    $key);
            }, ARRAY_FILTER_USE_KEY);

            if (count($filteredRdfData) > 1) {
                throw Exception::create(self::ERROR_MULTIPLE_LINK_METADATA_FOUND, [$path]);
            }

            if (count($filteredRdfData) > 0) {
                $linkMetaType = array_key_first($filteredRdfData);
                $type = substr($linkMetaType, strrpos($linkMetaType, '#') + 1);

                $linkMetaValue = reset($filteredRdfData);
                $value = array_pop($linkMetaValue);
                $url = $value['value'] ?? null;

                if (strpos($foundPath, './') === 0) {
                    // Filepath is relative to the meta file
                    $path = $foundPath;
                }

                if ($path !== $foundPath) {
                    // Change the path from the request to the redirect (or not found) path
                    $url = substr_replace($path,
                        $url,
                        strpos($path, $foundPath),
                        strlen($foundPath))
                    ;
                }

                $linkMeta = [
                    'type' => $type,
                    'url' => $url,
                ];
            }
        }

        return $linkMeta;
    }

    private function findPath(array $rdfPaths, string $path)
    {
        $path = ltrim($path, '/');

        foreach ($rdfPaths as $rdfPath) {
            if (
                strrpos($path, $rdfPath) === 0
                && $this->filesystem->has($rdfPath)
            ) {
                // @FIXME: We have no way of knowing if the file is a directory or a file.
                //         This means that, unless we make a trialing slash `/` required,
                //         (using the example for `forget.ttl`) forget.ttl/foo.txt will
                //         also work although semantically it should not
                $path = $rdfPath;
                break;
            }
        }

        return $path;
    }

    private function removeLinkFromMetaFileFor($path): bool
    {
        $result = false;

        if ($this->hasDescribedBy($path)) {
            $describedByPath = $this->getDescribedByPath($path);

            $graph = $this->getGraph();

            try {
                $contents = $this->filesystem->read($describedByPath);
                $graph->parse($contents, 'turtle', '/'.$describedByPath);
            } catch (\Throwable $e) {
                return false;
            }

            // A resource might be added for a folder but written to a file,
            // or vice-versa. In both cases, the _other_ entry also needs to be
            // removed. And depending on the RDF entry, the resource might have
            // a leading slash or not, so that also needs to be checked.
            $normalizedPath = trim($path, '/');
            $resourcePaths = array_unique([
                $normalizedPath,
                $normalizedPath . '/',
                '/' . $normalizedPath,
                '/' . $normalizedPath . '/',
            ]);

            // @CHECKME: If an entry for a sub-folder is present but then a file is written,
            //           removing the folder, should the sub-folder entry also be removed?

            $changed = false;
            foreach ($resourcePaths as $resourcePath) {
                $resource = $graph->resource($resourcePath);

                $predicates = $resource->propertyUris();
                foreach ($predicates as $predicate) {
                    if (strpos($predicate, 'https://purl.org/pdsinterop/link-metadata#') === 0) {
                        $changed = true;
                        $graph->deleteSingleProperty($resource, $predicate);
                    }
                }
            }

            if ($changed) {
                $changedContents = $graph->serialise('turtle');
                try {
                    $result = $this->filesystem->update($describedByPath, $changedContents);
                } catch (FileNotFoundException $exception) {
                    // $result is already false;
                }
            }
        }

        return $result;
    }
}
