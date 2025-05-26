<?php
namespace OCA\Solid\Controller;

use OCA\Solid\DpopFactoryTrait;
use OCA\Solid\PlainResponse;
use OCA\Solid\Notifications\SolidNotifications;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;

use Pdsinterop\Solid\Auth\WAC;
use Pdsinterop\Solid\Resources\Server as ResourceServer;

class ContactsController extends Controller
{
	use DpopFactoryTrait;

	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	public function __construct(
		$AppName,
		IRequest $request,
		ISession $session,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		$userId,
		IConfig $config,
		\OCA\Solid\Service\UserService $UserService,
		IDBConnection $connection,
	) {
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;

		$this->setJtiStorage($connection);
	}

	private function getFileSystem($userId) {
		// Make sure the root folder has an acl file, as is required by the spec;
		// Generate a default file granting the owner full access.
		$defaultAcl = $this->generateDefaultAcl($userId);

		// Create the Nextcloud Contacts Adapter
		$adapter = new \Pdsinterop\Flysystem\Adapter\NextcloudContacts($userId, $defaultAcl);

		$graph = new \EasyRdf\Graph();

		// Create Formats objects
		$formats = new \Pdsinterop\Rdf\Formats();

		$serverParams = $this->rawRequest->getServerParams();
		$scheme = $serverParams['REQUEST_SCHEME'];
		$domain = $serverParams['SERVER_NAME'];
		$path = $serverParams['REQUEST_URI'];
		$serverUri = "{$scheme}://{$domain}{$path}"; // FIXME: doublecheck that this is the correct url;

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
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me";
	}
	private function getContactsUrl($userId) {
		$contactsUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.contacts.handleHead", array("userId" => $userId, "path" => "foo")));
		$contactsUrl = preg_replace('/foo$/', '', $contactsUrl);
		return $contactsUrl;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRequest($userId, $path) {
		$this->contactsUserId = $userId;

		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->filesystem = $this->getFileSystem($userId);

		$this->resourceServer = new ResourceServer($this->filesystem, $this->response);		
		$this->WAC = new WAC($this->filesystem);

		$request = $this->rawRequest;
		$baseUrl = $this->getContactsUrl($userId);		
		$this->resourceServer->setBaseUrl($baseUrl);
		$this->WAC->setBaseUrl($baseUrl);
		$notifications = new SolidNotifications();
		$this->resourceServer->setNotifications($notifications);

		$dpop = $this->getDpop();

		try {
			$webId = $dpop->getWebId($request);
		} catch(\Pdsinterop\Solid\Auth\Exception\Exception $e) {
			$response = $this->resourceServer->getResponse()
				->withStatus(Http::STATUS_CONFLICT, "Invalid token " . $e->getMessage());
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
                // - if we have user subdomains enabled:
                //    /index.php/apps/solid/contacts{path}
                // and otherwise:
                //   index.php/apps/solid/~{userId}/contacts{path}

		// In the first case, we'll get the username from the SERVER_NAME. In the latter, it will come from the URL;
                if ($this->config->getUserSubDomainsEnabled()) {
                        $pathInfo = explode("contacts/", $_SERVER['REQUEST_URI']);
                        $path = $pathInfo[1];
                        $userId = explode(".", $_SERVER['SERVER_NAME'])[0];
                } else {
                        $pathInfo = explode("~", $_SERVER['REQUEST_URI']);
                        $pathInfo = explode("/", $pathInfo[1], 2);
                        $userId = $pathInfo[0];
                        $path = $pathInfo[1];
                        $path = preg_replace("/^contacts/", "", $path);
                }

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

		$result = new PlainResponse($body);

		foreach ($headers as $header => $values) {
			foreach ($values as $value) {
				$result->addHeader($header, $value);
			}
		}

		$result->setStatus($statusCode);
		return $result;
	}
}
