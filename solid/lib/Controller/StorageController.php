<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\Files\IRootFolder;
use OCP\Files\IHomeStorage;
use OCP\Files\SimpleFS\ISimpleRoot;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use Pdsinterop\Solid\Resources\Server as ResourceServer;

class PlainResponse extends Response {
	// FIXME: We might as well add a PSRResponse class to handle those;
	
	/**
	 * response data
	 * @var array|object
	 */
	protected $data;

	/**
	 * constructor of PlainResponse
	 * @param array|object $data the object or array that should be transformed
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($data='', $statusCode=Http::STATUS_OK) {
		parent::__construct();
		$this->data = $data;
		$this->setStatus($statusCode);
		$this->addHeader('Content-Type', 'text/html; charset=utf-8');
	}

	/**
	 * Returns the data unchanged
	 * @return string the data (unchanged)
	 */
	public function render() {
		$response = $this->data;
		return $response;
	}

	/**
	 * Sets the data for the response
	 * @return PlainResponse Reference to this object
	 */
	public function setData($data) {
		$this->data = $data;
		return $this;
	}

	/**
	 * Used to get the set parameters
	 * @return response data
	 */
	public function getData() {
		return $this->data;
	}
}

class StorageController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	public function __construct($AppName, IRootFolder $rootFolder, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, ServerConfig $config, \OCA\Solid\Service\UserService $UserService) 
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
	}

	private function getFileSystem() {
		// FIXME: this should be replaced with a nextcloud flysystem adapter with the correct path;
		$filesystemRoot = __DIR__ . '/../../../../data' . $this->solidFolder->getPath();

		$adapter = new \League\Flysystem\Adapter\Local($filesystemRoot);

		$graph = new \EasyRdf_Graph();

		// Create Formats objects
		$formats = new \Pdsinterop\Rdf\Formats();

		$serverUri = "https://" . $this->rawRequest->getServerParams()["SERVER_NAME"] . $this->rawRequest->getServerParams()["REQUEST_URI"]; // FIXME: doublecheck that this is the correct url;

		// Create the RDF Adapter
		$rdfAdapter = new \Pdsinterop\Rdf\Flysystem\Adapter\Rdf(
			$adapter,
			$graph,
			$formats,
			$serverUri
		);
		
		$filesystem = new \League\Flysystem\Filesystem($rdfAdapter);

		$filesystem->addPlugin(new \Pdsinterop\Rdf\Flysystem\Plugin\AsMime($formats));
		
		$plugin = new \Pdsinterop\Rdf\Flysystem\Plugin\ReadRdf($graph);
		$filesystem->addPlugin($plugin);

		return $filesystem;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRequest($userId, $path) {
		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		if (!$this->userFolder->nodeExists("solid")) {
			$this->userFolder->newFolder("solid"); // Create the Solid directory for storage if it doesn't exist.
		}
		$this->solidFolder = $this->userFolder->get("solid");

		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->filesystem = $this->getFileSystem();
		$this->resourceServer = new ResourceServer($this->filesystem, $this->response);		

		$uri = $this->urlGenerator->getBaseURL() . "/" . $path;
		$this->serverRequest = new \Laminas\Diactoros\ServerRequest(array(),array(), $uri);
		$request = $this->rawRequest->withUri($this->serverRequest->getUri());
		
		$response = $this->resourceServer->respondToRequest($request);	
		return $this->respond($response);
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleGet($userId, $path) {	
		return $this->handleRequest($userId, $path);
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handlePost($userId, $path) {
		return $this->handleRequest($userId, $path);
	}
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handlePut() { // $userId, $path) {
		// FIXME: Adding the correct variables in the function name will make nextcloud
		// throw an error about accessing put twice, so we will find out the userId and path from $_SERVER instead;
		
		// because we got here, the request uri should look like:
		// /index.php/apps/solid/@{userId}/storage{path}
		$pathInfo = explode("@", $_SERVER['REQUEST_URI']);
		$pathInfo = explode("/", $pathInfo[1], 2);
		$userId = $pathInfo[0];
		$path = $pathInfo[1];
		$path = preg_replace("/^storage/", "", $path);
		
		return $this->handleRequest($userId, $path);
	}
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleDelete($userId, $path) {
		return $this->handleRequest($userId, $path);
	}
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleHead($userId, $path) {
		return $this->handleRequest($userId, $path);
	}

	private function respond($response) {
		$statusCode = $response->getStatusCode();
		$response->getBody()->rewind();
		$headers = $response->getHeaders();

		$body = $response->getBody()->getContents();
		if ($statusCode > 399) {
			$reason = $response->getReasonPhrase();
			$result = new JSONResponse($reason, $statusCode);
			return $result;
		}

		$result = new PlainResponse($body); // FIXME: we need a way to just return a plain response;

		foreach ($headers as $header => $values) {
			foreach ($values as $value) {
				$result->addHeader($header, $value);
			}
		}
		
		$result->setStatus($statusCode);
		return $result;
	}
}
