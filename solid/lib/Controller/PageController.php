<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Contacts\IManager;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use Laminas\Diactoros\ServerRequest;

class PageController extends Controller {
	private $userId;
	private $userManager;
	private $urlGenerator;
	private $config;

	public function __construct($AppName, IRequest $request, ServerConfig $config, IUserManager $userManager, IManager $contactsManager, IURLGenerator $urlGenerator, $userId){
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->contactsManager = $contactsManager;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$args = array(
			'navigation'  => array(
				"profile" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.profile", array("userId" => $this->userId))),
				"launcher" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.app.appLauncher", array())),
			)
		);
		return new TemplateResponse('solid', 'index', $args);  // templates/index.php
	}

	private function getUserProfile($userId) {
		if ($this->userManager->userExists($userId)) {
			$user = $this->userManager->get($userId);
			$addressBooks = $this->contactsManager->getAddressBooks();
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
				'profileUri'  => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.handleProfileGet", array("userId" => $userId))) . "#me",
				'friends' => $friends,
				'inbox' => 'storage/inbox/',
				'preferences' => 'storage/settings/preferences.ttl',
				'privateTypeIndex' => 'storage/settings/privateTypeIndex.ttl',
				'publicTypeIndex' => 'storage/settings/publicTypeIndex.ttl',
				'storage' => 'storage/',
				'navigation'  => array(
					"profile" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.profile", array("userId" => $this->userId))),
					"launcher" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.app.appLauncher", array())),
				),
	/*
				'trustedApps' => array(
					array(
						'origin' => 'https://localhost:3002',
						'grants' => array(
							'http://www.w3.org/ns/auth/acl#Read',
							'http://www.w3.org/ns/auth/acl#Write',
							'http://www.w3.org/ns/auth/acl#Append',
							'http://www.w3.org/ns/auth/acl#Control'
						)
					)
				)
*/
			);
			return $profile;
		}
		return false;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function profile($userId) {
		// header("Access-Control-Allow-Headers: *, authorization, accept, content-type");
		// header("Access-Control-Allow-Credentials: true");
		$profile = $this->getUserProfile($userId);
		if (!$profile) {
		   return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
		}
		$templateResponse = new TemplateResponse('solid', 'profile', $profile);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedStyleDomain("data:");
		$templateResponse->setContentSecurityPolicy($policy);
		return $templateResponse;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function approval($clientId) {
		$clientRegistration = $this->config->getClientRegistration($clientId);
		$params = array(
			"clientId" => $clientId,
			"clientName" => $clientRegistration['client_name'],
			"serverName" => "Nextcloud",
			"returnUrl" => $_GET['returnUrl'],
		);
		$templateResponse = new TemplateResponse('solid', 'sharing', $params);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedStyleDomain("data:");

		$parsedOrigin = parse_url($clientRegistration['redirect_uris'][0]);
		$origin = $parsedOrigin['host'];
		if ($origin) {
			$policy->addAllowedFormActionDomain($origin);
			$templateResponse->setContentSecurityPolicy($policy);
		}
		return $templateResponse;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleApproval($clientId) {
		$approval = $_POST['approval'];
		if ($approval == "allow") {
			$this->config->addAllowedClient($this->userId, $clientId);
		} else {
			$this->config->removeAllowedClient($this->userId, $clientId);
		}
		$authUrl = $_POST['returnUrl'];

		$result = new JSONResponse("ok");
		$result->setStatus("302");
		$result->addHeader("Location", $authUrl);
		return $result;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleRevoke($clientId) {
		$this->config->removeAllowedClient($this->userId, $clientId);
		$result = new JSONResponse("ok");
		return $result;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleProfileRequest($userId, $method) {
		$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
		$this->response = new \Laminas\Diactoros\Response();

		$this->WAC = new WAC($this->filesystem);
		$this->DPop = new DPop();

		$request = $this->rawRequest;

		try {
			$webId = $this->DPop->getWebId($request);
		} catch(\Exception $e) {
			return new JSONResponse("Invalid token", 409);
		}
		$origin = $request->getHeaderLine("Origin");
		$allowedClients = $this->config->getAllowedClients($userId);
		$allowedOrigins = array();
		foreach ($allowedClients as $clientId) {
			$clientRegistration = $this->config->getClientRegistration($clientId);
			$allowedOrigins[] = $clientRegistration['client_name'];
		}
		if (!$this->WAC->isAllowed($request, $webId, $origin, $allowedOrigins)) {
			return new JSONResponse("Access denied", 403);
		}

		$contentType = $rawRequest->getHeaderLine("Content-Type");
		switch ($method) {
			case "GET":
				$turtleProfile = $this->generateTurtleProfile($userId);
				if (!$turtleProfile) {
				   return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
				}
				return new TemplateResponse(
					'solid',
					'turtle-profile',
					array("turtleProfile" => $turtleProfile),
					'blank'
				);
			break;
			case "PUT":
				switch ($contentType) {
					case "text/turtle":
					break;
					default:
						return new JSONResponse(array(), 400);
					break;
				}
				$contents = $rawRequest->getBody()->getContents();
				$this->config->setProfileData($userId, $contents);
				return new JSONResponse("ok", 200);
			break;
			case "PATCH":
				switch ($contentType) {
					case "application/sparql-update":
						$contents = $rawRequest->getBody()->getContents();
						$profile = $this->generateTurtleProfile($userId);
		
						$graph = new \EasyRdf_Graph();
						$graph->parse($profile, "turtle");
						if (preg_match_all("/((INSERT|DELETE).*{(.*)})+/", $contents, $matches, PREG_SET_ORDER)) {
							foreach ($matches as $match) {
								$command = $match[2];
								$triples = $match[3];
			
								// apply changes to ttl data
								switch($command) {
									case "INSERT":
										// insert $triple(s) into $graph
										$graph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;
			
									break;
									case "DELETE":
										// delete $triples from $graph
										$deleteGraph = new \EasyRdf_Graph();
										$deleteGraph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;
										$resources = $deleteGraph->resources();
										foreach ($resources as $resource) {
											$properties = $resource->propertyUris();
											foreach ($properties as $property) {
												$values = $resource->all($property);
												if (!sizeof($values)) {
													$graph->delete($resource, $property);
												} else {
													foreach ($values as $value) {
														$count = $graph->delete($resource, $property, $value);
														if ($count == 0) {
															throw new \Exception("Could not delete a value", 500);
														}
													}
												}
											}
										}
									break;
									default:
										throw new \Exception("Unimplemented SPARQL", 500);
									break;
								}
							}
						}
		
						// Assuming this is in our native format, turtle
						$patchedProfile = $graph->serialise("turtle");
						$this->config->setProfileData($userId, $patchedProfile);
						return new JSONResponse("ok", 200);
					break;
					default:
						return new JSONResponse(array(), 400);
					break;
				}
			break;
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleProfileGet($userId) {
		return $this->handleProfileRequest($userId, "GET");
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleProfilePut($userId) {
		return $this->handleProfileRequest($userId, "PUT");
	}
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function handleProfilePatch($userId) {
		return $this->handleProfilePatch($userId, "PATCH");
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
	
	pro:turtle a foaf:PersonalProfileDocument; foaf:maker :me; foaf:primaryTopic :me.
	
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

		$baseProfile = $this->config->getProfileData($userId);
		$graph = new \EasyRdf_Graph();
		$graph->parse($baseProfile, "turtle");
		$graph->parse($generatedProfile, "turtle");
		$combinedProfile = $graph->serialize("turtle");
		return $combinedProfile;
	}
}
