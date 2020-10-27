<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IContactsManager;
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

	public function __construct($AppName, IRequest $request, ServerConfig $config, IUserManager $userManager, IContactsManager $contactsManager, IURLGenerator $urlGenerator, $userId){
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
			$profile = array(
				'id' => $userId,
				'displayName' => $user->getDisplayName(),
				'profileUri'  => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.turtleProfile", array("userId" => $userId))) . "#me"
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
		return new TemplateResponse('solid', 'turtle-profile', $profile, 'blank');
	}
}
