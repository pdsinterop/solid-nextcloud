<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

class PageController extends Controller {
	private $userId;
	private $userManager;
	private $urlGenerator;
	private $config;

	public function __construct($AppName, IRequest $request, ServerConfig $config, IUserManager $userManager, IURLGenerator $urlGenerator, $userId){
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->userId = $userId;
		$this->userManager = $userManager;
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
			$profile = array(
				'id' => $userId,
				'displayName' => $user->getDisplayName(),
				'profileUri'  => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $userId, "path" => "/card"))) . "#me",
				'navigation'  => array(
					"profile" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.profile", array("userId" => $this->userId))),
					"launcher" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.app.appLauncher", array())),
				)
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
}
