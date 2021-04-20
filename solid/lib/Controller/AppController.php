<?php
namespace OCA\Solid\Controller;

use OCA\Solid\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Contacts\IManager;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

class AppController extends Controller {
	private $userId;
	private $userManager;
	private $urlGenerator;
	private $config;

	public function __construct($AppName, IRequest $request, IConfig $config, IUserManager $userManager, IManager $contactsManager, IURLGenerator $urlGenerator, $userId){
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->contactsManager = $contactsManager;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
	}

    private function getUserApps($userId) {   
		$userApps = [];
		if ($this->userManager->userExists($userId)) {
            $allowedClients = $this->config->getAllowedClients($userId);
			foreach ($userClients as $clientId) {
				$registration = $this->config->getClientRegistration($clientId);
				$userApps[] = $registration['client_name'];
			}
		}
		return $userApps;
    }

	private function getAppsList() {
        $path = __DIR__ . "/../solid-app-list.json";
        $appsListJson = file_get_contents($path);
		$appsList = json_decode($appsListJson, true);
		
		$userApps = $this->getUserApps($this->userId);

		foreach ($appsList as $key => $app) {
			$parsedOrigin = parse_url($app['launchUrl']);
			$origin = $parsedOrigin['host'];
			if (in_array($userApps, $origin)) {
				$appsList[$key]['registered'] = 1;
			} else {
				$appsList[$key]['registered'] = 0;
			}
		}
        return $appsList;
	}

	private function getProfilePage() {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.profile.handleGet", array("userId" => $this->userId, "path" => "/card"))) . "#me";
	}
	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function appLauncher() {
        $appsList = $this->getAppsList();
		if (!$appsList) {
		   return new JSONResponse(array(), Http::STATUS_NOT_FOUND);
		}
        $appLauncherData = array(
			"appsListJson" => json_encode($appsList),
			"webId" => json_encode($this->getProfilePage()),
			"storageUrl" => json_encode($this->getStorageUrl($this->userId)),
			'solidNavigation'  => array(
				"profile" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.page.profile", array("userId" => $this->userId))),
				"launcher" => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.app.appLauncher", array())),
			)
		);
		$templateResponse = new TemplateResponse('solid', 'applauncher', $appLauncherData);
        $policy = new ContentSecurityPolicy();
        $policy->addAllowedStyleDomain("data:");
        $policy->addAllowedScriptDomain("'self'");
        $policy->addAllowedScriptDomain("'unsafe-inline'");
        $policy->addAllowedScriptDomain("'unsafe-eval'");
		$templateResponse->setContentSecurityPolicy($policy);
		return $templateResponse;
	}
}