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
		return new TemplateResponse('solid', 'index');  // templates/index.php
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
				'profileUri'  => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.turtleProfile", array("userId" => $userId))) . "#me",
				'friends' => $friends,
				'inbox' => 'storage/inbox/',
				'preferences' => 'storage/settings/preferences.ttl',
				'privateTypeIndex' => 'storage/settings/privateTypeIndex.ttl',
				'publicTypeIndex' => 'storage/settings/publicTypeIndex.ttl',
				'storage' => 'storage/',
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
	 * @CORS
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
		$templateResponse->setContentSecurityPolicy($policy);
		return $templateResponse;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
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
	 * @CORS
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
     * @CORS
     */
	public function turtleProfile($userId) {
		$profile = $this->getUserProfile($userId);
		if (!$profile) {
		   return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
        }
		header("Access-Control-Allow-Headers: authorization");
		header("Access-Control-Allow-Credentials: true");
		return new TemplateResponse('solid', 'turtle-profile', $profile, 'blank');
	}
}
