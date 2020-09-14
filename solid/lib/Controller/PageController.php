<?php
namespace OCA\Solid\Controller;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

class PageController extends Controller {
	private $userId;
	private $userManager;

	public function __construct($AppName, IRequest $request, IUserManager $userManager, $userId){
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->request     = $request;
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
				'profileUri'  => 'http://nextcloud.local/apps/solid/@' . $userId . '/turtle#me'
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
	public function turtleProfile($userId) {
		$profile = $this->getUserProfile($userId);
		if (!$profile) {
		   return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
        }
		return new TemplateResponse('solid', 'turtle-profile', $profile, 'blank');
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function cors($path) {
		return true;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function openid() {
		return new JSONResponse(
			array(
				'issuer' => 'https://localhost',
				'authorization_endpoint' => 'https://localhost/apps/solid/authorize'
				)
		);
	}	
}