<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCA\Solid\PlainResponse;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;
use Pdsinterop\Solid\Resources\Server as ResourceServer;
use Pdsinterop\Solid\Auth\Utils\DPop as DPop;
use Pdsinterop\Solid\Auth\WAC as WAC;

class CalendarController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	public function __construct($AppName, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, ServerConfig $config, \OCA\Solid\Service\UserService $UserService) 
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = $config;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
	}

	private function getFileSystem($userId) {
		// Make sure the root folder has an acl file, as is required by the spec;
        // Generate a default file granting the owner full access.
		$defaultAcl = $this->generateDefaultAcl($userId);

		// Create the Nextcloud Calendar Adapter
		$adapter = new \Pdsinterop\Flysystem\Adapter\NextcloudCalendar($userId, $defaultAcl);

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

	private function generateDefaultAcl($userId) {
		$defaultAcl = <<< EOF
# Root ACL resource for the user account
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

# The owner has full access to every resource in their pod.
# Other agents have no access rights,
# unless specifically authorized in other .acl resources.
<#owner>
	a acl:Authorization;
	acl:agent <{user-profile-uri}>;
	# Set the access to the root storage folder itself
	acl:accessTo <./>;
	# All resources will inherit this authorization, by default
	acl:default <./>;
	# The owner has all of the access modes allowed
	acl:mode
		acl:Read, acl:Write, acl:Control.
EOF;

		$profileUri = $this->getUserProfile($userId);
		$defaultAcl = str_replace("{user-profile-uri}", $profileUri, $defaultAcl);
		return $defaultAcl;
	}

	private function getUserProfile($userId) {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/turtle"))) . "#me";
	}
	private function getCalendarUrl($userId) {
		$calendarUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.calendar.handleHead", array("userId" => $userId, "path" => "foo")));
		$calendarUrl = preg_replace('/foo$/', '', $calendarUrl);
		return $calendarUrl;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRequest($userId, $path) {
        $this->calendarUserId = $userId;
        
		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->filesystem = $this->getFileSystem($userId);

		$this->resourceServer = new ResourceServer($this->filesystem, $this->response);		
        $this->WAC = new WAC($this->filesystem);
		$this->DPop = new DPop();

		$request = $this->rawRequest;
		$baseUrl = $this->getCalendarUrl($userId);		
		$this->resourceServer->setBaseUrl($baseUrl);
		$this->WAC->setBaseUrl($baseUrl);
		$pubsub = getenv('PUBSUB_URL') ?: ("http://pubsub:8080/");
		$this->resourceServer->setPubSubUrl($pubsub);

		try {
			$webId = $this->DPop->getWebId($request);
		} catch(\Exception $e) {
			$response = $this->resourceServer->getResponse()->withStatus(409, "Invalid token");
			return $this->respond($response);
		}
		
		if (!$this->WAC->isAllowed($request, $webId)) {
			$response = $this->resourceServer->getResponse()->withStatus(403, "Access denied");
			return $this->respond($response);
		}

		$response = $this->resourceServer->respondToRequest($request);	
		$response = $this->WAC->addWACHeaders($request, $response, $webId);
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
		$path = preg_replace("/^calendar/", "", $path);
		
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
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handlePatch($userId, $path) {
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