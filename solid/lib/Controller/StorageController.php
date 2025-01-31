<?php
namespace OCA\Solid\Controller;

use OCA\Solid\DpopFactoryTrait;
use OCA\Solid\BearerFactoryTrait;
use OCA\Solid\PlainResponse;
use OCA\Solid\Notifications\SolidNotifications;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;

use OCP\AppFramework\Http\EmptyContentSecurityPolicy;

use Pdsinterop\Solid\Auth\WAC;
use Pdsinterop\Solid\Resources\Server as ResourceServer;

class StorageController extends Controller
{
	use DpopFactoryTrait;
	use BearerFactoryTrait;

	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;

	public function __construct(
		$AppName,
		IRootFolder $rootFolder,
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
		$this->rootFolder = $rootFolder;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;

		$this->setJtiStorage($connection);
	}

	private function getFileSystem() {
		// Create the Nextcloud Adapter
		$adapter = new \Pdsinterop\Flysystem\Adapter\Nextcloud($this->solidFolder);
		$graph = new \EasyRdf\Graph();

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

	private function getUserProfile($userId) {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me";
	}
	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}
	private function generateDefaultAcl($userId) {
		$defaultAcl = <<< EOF
# Root ACL resource for the user account
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

# The homepage is readable by the public
<#public>
    a acl:Authorization;
    acl:agentClass foaf:Agent;
    acl:accessTo <./>;
	acl:mode acl:Read.

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

	private function generatePublicAppendAcl($userId) {
		$publicAppendAcl = <<< EOF
# Inbox ACL resource for the user account
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

<#public>
        a acl:Authorization;
        acl:agentClass foaf:Agent;
        acl:accessTo <./>;
        acl:default <./>;
        acl:mode
				acl:Append.

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
		$publicAppendAcl = str_replace("{user-profile-uri}", $profileUri, $publicAppendAcl);
		return $publicAppendAcl;
	}

	private function generatePublicReadAcl($userId) {
		$publicReadAcl = <<< EOF
# Inbox ACL resource for the user account
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

<#public>
	a acl:Authorization;
	acl:agentClass foaf:Agent;
	acl:accessTo <./>;
	acl:default <./>;
	acl:mode
		acl:Read.

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
		$publicReadAcl = str_replace("{user-profile-uri}", $profileUri, $publicReadAcl);
		return $publicReadAcl;
	}

	private function generateDefaultPublicTypeIndex() {
		$publicTypeIndex = <<< EOF
# Public type index
@prefix : <#>.
@prefix solid: <http://www.w3.org/ns/solid/terms#>.

<>
	a solid:ListedDocument, solid:TypeIndex.
EOF;

		return $publicTypeIndex;
	}

	private function generateDefaultPrivateTypeIndex() {
		$privateTypeIndex = <<< EOF
# Private type index
@prefix : <#>.
@prefix solid: <http://www.w3.org/ns/solid/terms#>.

<>
	a solid:UnlistedDocument, solid:TypeIndex.
EOF;

		return $privateTypeIndex;
	}
	private function generateDefaultPreferences($userId) {
		$preferences = <<< EOF
# Preferences
@prefix : <#>.
@prefix sp: <http://www.w3.org/ns/pim/space#>.
@prefix dct: <http://purl.org/dc/terms/>.
@prefix profile: <{user-profile-uri}>.
@prefix solid: <http://www.w3.org/ns/solid/terms#>.

<>
	a sp:ConfigurationFile;
	dct:title "Preferences file".

profile:me
	a solid:Developer;
	solid:privateTypeIndex <privateTypeIndex.ttl>;
	solid:publicTypeIndex <publicTypeIndex.ttl>.
EOF;

		$profileUri = $this->getUserProfile($userId);
		$preferences = str_replace("{user-profile-uri}", $profileUri, $preferences);
		return $preferences;
	}
	private function initializeStorage($userId) {
		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		if (!$this->userFolder->nodeExists("solid")) {
			$this->userFolder->newFolder("solid"); // Create the Solid directory for storage if it doesn't exist.
		}
		$this->solidFolder = $this->userFolder->get("solid");

		$this->filesystem = $this->getFileSystem();

		// Make sure the root folder has an acl file, as is required by the spec;
		// Generate a default file granting the owner full access if there is nothing there.
		if (!$this->filesystem->has("/.acl")) {
			$defaultAcl = $this->generateDefaultAcl($userId);
			$this->filesystem->write("/.acl", $defaultAcl);
		}

		// Generate default folders and ACLs:
		if (!$this->filesystem->has("/inbox")) {
			$this->filesystem->createDir("/inbox");
		}
		if (!$this->filesystem->has("/inbox/.acl")) {
			$inboxAcl = $this->generatePublicAppendAcl($userId);
			$this->filesystem->write("/inbox/.acl", $inboxAcl);
		}
		if (!$this->filesystem->has("/settings")) {
			$this->filesystem->createDir("/settings");
		}
		if (!$this->filesystem->has("/settings/privateTypeIndex.ttl")) {
			$privateTypeIndex = $this->generateDefaultPrivateTypeIndex();
			$this->filesystem->write("/settings/privateTypeIndex.ttl", $privateTypeIndex);
		}
		if (!$this->filesystem->has("/settings/publicTypeIndex.ttl")) {
			$publicTypeIndex = $this->generateDefaultPublicTypeIndex();
			$this->filesystem->write("/settings/publicTypeIndex.ttl", $publicTypeIndex);
		}
		if (!$this->filesystem->has("/settings/preferences.ttl")) {
			$preferences = $this->generateDefaultPreferences($userId);
			$this->filesystem->write("/settings/preferences.ttl", $preferences);
		}
		if (!$this->filesystem->has("/public")) {
			$this->filesystem->createDir("/public");
		}
		if (!$this->filesystem->has("/public/.acl")) {
			$publicAcl = $this->generatePublicReadAcl($userId);
			$this->filesystem->write("/public/.acl", $publicAcl);
		}
		if (!$this->filesystem->has("/private")) {
			$this->filesystem->createDir("/private");
		}
	}
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRequest($userId, $path) {
		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->initializeStorage($userId);

		$this->resourceServer = new ResourceServer($this->filesystem, $this->response);
		$this->WAC = new WAC($this->filesystem);

		$request = $this->rawRequest;
		$baseUrl = $this->getStorageUrl($userId);		
		$this->resourceServer->setBaseUrl($baseUrl);
		$this->WAC->setBaseUrl($baseUrl);

		$notifications = new SolidNotifications();
		$this->resourceServer->setNotifications($notifications);

		$dpop = $this->getDpop();

		$error = false;
		try {
			$webId = $dpop->getWebId($request);
		} catch(\Pdsinterop\Solid\Auth\Exception\Exception $e) {
			$error = $e;
		}

		if (!isset($webId)) {
			$bearer = $this->getBearer();
			try {
				$webId = $bearer->getWebId($request);
			} catch(\Pdsinterop\Solid\Auth\Exception\Exception $e) {
				$error = $e;
			}
		}

		if (!isset($webId)) {
			$response = $this->resourceServer->getResponse()
				->withStatus(Http::STATUS_CONFLICT, "Invalid token");
			return $this->respond($response);
		}

		$origin = $request->getHeaderLine("Origin");
		$allowedClients = $this->config->getAllowedClients($userId);
		$allowedOrigins = array();
		foreach ($allowedClients as $clientId) {
			$clientRegistration = $this->config->getClientRegistration($clientId);
			if (isset($clientRegistration['client_name'])) {
				$allowedOrigins[] = $clientRegistration['client_name'];
			}
			if (isset($clientRegistration['origin'])) {
				$allowedOrigins[] = $clientRegistration['origin'];
			}
		}
		if (!$this->WAC->isAllowed($request, $webId, $origin, $allowedOrigins)) {
			$response = $this->resourceServer->getResponse()
			->withStatus(403, "Access denied");
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

		$result = new PlainResponse($body);

		foreach ($headers as $header => $values) {
			$result->addHeader($header, implode(", ", $values));
		}

//		$origin = $_SERVER['HTTP_ORIGIN'];
//		$result->addHeader('Access-Control-Allow-Credentials', 'true');
//		$result->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
//		$result->addHeader('Access-Control-Allow-Origin', $origin);
		
                $policy = new EmptyContentSecurityPolicy();
                $policy->addAllowedStyleDomain("*");
                $policy->addAllowedStyleDomain("data:");
                $policy->addAllowedScriptDomain("*");
                $policy->addAllowedImageDomain("*");
                $policy->addAllowedFontDomain("*");
                $policy->addAllowedConnectDomain("*");
                $policy->allowInlineStyle(true);
                // $policy->allowInlineScript(true); - removed, this function no longer exists in NC28
                $policy->allowEvalScript(true);
                $result->setContentSecurityPolicy($policy);
                
                $result->setStatus($statusCode);
		return $result;
	}
}
