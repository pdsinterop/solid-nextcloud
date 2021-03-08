<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCA\Solid\PlainResponse;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\Contacts\IManager;
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


class ProfileController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	public function __construct($AppName, IRequest $request, ISession $session, IManager $contactsManager, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, ServerConfig $config, \OCA\Solid\Service\UserService $UserService) 
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = $config;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->contactsManager = $contactsManager;
		$this->session = $session;
	}

	private function getFileSystem($userId) {
		// Make sure the root folder has an acl file, as is required by the spec;
        // Generate a default file granting the owner full access.
		$defaultAcl = $this->generateDefaultAcl($userId);
		$profile = $this->generateTurtleProfile($userId);

		// Create the Nextcloud Calendar Adapter
		$adapter = new \Pdsinterop\Flysystem\Adapter\NextcloudProfile($userId, $profile, $defaultAcl, $this->config);

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

# The profile is readable by the public
<#public>
    a acl:Authorization;
    acl:agentClass foaf:Agent;
	acl:accessTo <./>;
	acl:default <./>;
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

		$profileUri = $this->getUserProfileUri($userId);
		$defaultAcl = str_replace("{user-profile-uri}", $profileUri, $defaultAcl);
		return $defaultAcl;
	}

	private function getUserProfileUri($userId) {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me";
	}
	private function getProfileUrl($userId) {
		$profileUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleHead", array("userId" => $userId, "path" => "foo")));
		$profileUrl = preg_replace('/foo$/', '', $profileUrl);
		return $profileUrl;
	}
	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRequest($userId, $path) {
        $this->userId = $userId;
        
		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->filesystem = $this->getFileSystem($userId);

		$this->resourceServer = new ResourceServer($this->filesystem, $this->response);		
        $this->WAC = new WAC($this->filesystem);
		$this->DPop = new DPop();

		$request = $this->rawRequest;
		$baseUrl = $this->getProfileUrl($userId);		
		$this->resourceServer->setBaseUrl($baseUrl);
		$this->WAC->setBaseUrl($baseUrl);
		$pubsub = getenv('PUBSUB_URL') ?: ("http://pubsub:8080/");
		$this->resourceServer->setPubSubUrl($pubsub);

		if ($request->getHeaderLine("DPop")) {
			try {
				$webId = $this->DPop->getWebId($request);
			} catch(\Exception $e) {
				$response = $this->resourceServer->getResponse()->withStatus(409, "Invalid token");
				return $this->respond($response);
			}
		} else {
			$webId = "";
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
		$path = preg_replace("/^profile/", "", $path);
		
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

	private function getUserProfile($userId) {
		if ($this->userManager->userExists($userId)) {
			$user = $this->userManager->get($userId);
			$addressBooks = $this->contactsManager->getUserAddressBooks();
			$friends = [];
			foreach($addressBooks as $k => $v) {
			  $results = $addressBooks[$k]->search('', ['FN'], ['types' => true]);
			  foreach($results as $found) {
				  foreach($found['URL'] as $i => $obj) {
					array_push($friends, $obj['value']);
				  }
				}
			}
			$profile = array(
				'id' => $userId,
				'displayName' => $user->getDisplayName(),
				'profileUri'  => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me",
				'friends' => $friends,
				'inbox' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleGet", array("userId" => $userId, "path" => "/inbox/"))),
				'preferences' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleGet", array("userId" => $userId, "path" => "/settings/preferences.ttl"))),
				'privateTypeIndex' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleGet", array("userId" => $userId, "path" => "/settings/privateTypeIndex.ttl"))),
				'publicTypeIndex' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleGet", array("userId" => $userId, "path" => "/settings/publicTypeIndex.ttl"))),
				'storage' => $this->getStorageUrl($userId)
			);
			return $profile;
		}
		return false;
	}

	private function generateTurtleProfile($userId) {
		$profile = $this->getUserProfile($userId);
		if (!$profile) {
			return "";
		}
		ob_start();
	?>@prefix : <#>.
	@prefix solid: <http://www.w3.org/ns/solid/terms#>.
	@prefix pro: <./>.
	@prefix foaf: <http://xmlns.com/foaf/0.1/>.
	@prefix schem: <http://schema.org/>.
	@prefix acl: <http://www.w3.org/ns/auth/acl#>.
	@prefix ldp: <http://www.w3.org/ns/ldp#>.
	@prefix inbox: <<?php echo $profile['inbox']; ?>>.
	@prefix sp: <http://www.w3.org/ns/pim/space#>.
	@prefix ser: <<?php echo $profile['storage']; ?>>.
	
	pro:card a foaf:PersonalProfileDocument; foaf:maker :me; foaf:primaryTopic :me.
	
	:me
		a schem:Person, foaf:Person;
		ldp:inbox inbox:;
		sp:preferencesFile <<?php echo $profile['preferences']; ?>>;
		sp:storage ser:;
		solid:account ser:;
		solid:privateTypeIndex <<?php echo $profile['privateTypeIndex']; ?>>;
		solid:publicTypeIndex <<?php echo $profile['publicTypeIndex']; ?>>;
	<?php
	foreach ($profile['friends'] as $key => $friend) {
	?>
		foaf:knows <<?php echo $friend; ?>>;
	<?php
	}
	?>
		foaf:name "<?php echo $profile['displayName']; ?>";
		<http://www.w3.org/2006/vcard/ns#fn> "<?php echo $profile['displayName']; ?>".
	<?php
		$generatedProfile = ob_get_contents();
		ob_end_clean();

		$baseUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/turtle")));
		$baseProfile = $this->config->getProfileData($userId);
		$graph = new \EasyRdf_Graph();
		$graph->parse($baseProfile, "turtle", $baseUrl);
		$graph->parse($generatedProfile, "turtle", $baseUrl);
		$combinedProfile = $graph->serialise("turtle");
		return $combinedProfile;
	}
}