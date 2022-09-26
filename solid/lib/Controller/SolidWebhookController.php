<?php

namespace OCA\Solid\Controller;

use OCA\Solid\AppInfo\Application;
use OCA\Solid\Service\SolidWebhookService;
use OCA\Solid\ServerConfig;
use OCA\Solid\PlainResponse;
use OCA\Solid\Notifications\SolidNotifications;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\IHomeStorage;
use OCP\Files\SimpleFS\ISimpleRoot;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

use Pdsinterop\Solid\Resources\Server as ResourceServer;
use Pdsinterop\Solid\Auth\Utils\DPop as DPop;
use Pdsinterop\Solid\Auth\WAC as WAC;

class SolidWebhookController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	/** @var SolidWebhookService */
	private $webhookService;

	public function __construct($AppName, IRootFolder $rootFolder, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config, SolidWebhookService $webhookService)
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
		$this->rootFolder = $rootFolder;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
		$this->webhookService = $webhookService;

		$this->DPop = new DPop();
		try {
			$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
			$this->webId = $this->DPop->getWebId($this->rawRequest);
		} catch(\Exception $e) {
			return new PlainResponse("Invalid token", 409);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function listWebhooks(): DataResponse {
		return new DataResponse($this->webhookService->findAll($this->webId));
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function register(string $targetUrl, string $webhookUrl, string $expiry): DataResponse {
		// FIXME: Validate WAC read access to the target URL for $this->webId
		if ($this->checkReadAccess($targetUrl)) {
			return new DataResponse($this->webhookService->create($this->webId, $targetUrl, $webhookUrl, $expiry));
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function unregister(string $targetUrl): DataResponse {
		return $this->handleNotFound(function () use ($targetUrl) {
			return $this->webhookService->delete($this->webId, $targetUrl);
		});
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

	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}
	private function getAppBaseUrl() {
		$appBaseUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("solid.app.appLauncher"));
		return $appBaseUrl;
	}
	private function initializeStorage($userId) {
		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		$this->solidFolder = $this->userFolder->get("solid");
		$this->filesystem = $this->getFileSystem();
	}

	private function parseTargetUrl($targetUrl) {
		// targetUrl = https://nextcloud.server/solid/@alice/storage/foo/bar
		$appBaseUrl = $this->getAppBaseUrl(); //  https://nextcloud.server/solid/
		$internalUrl = str_replace($appBaseUrl, '', $targetUrl); // @alice/storage/foo/bar
		$pathicles = explode("/", $internalUrl);
		$userId = $pathicles[0]; // @alice
		$userId = preg_replace("/^@/", "", $userId); // alice
                $storageUrl = $this->getStorageUrl($userId); // https://nextcloud.server/solid/@alice/storage/
		$storagePath = str_replace($storageUrl, '/', $targetUrl); // /foo/bar
		return array(
			"userId" => $userId,
			"path" => $storagePath
		);
	}
	
	private function createGetRequest($targetUrl) {
		$serverParams = [];
		$fileParams = [];
		$method = "GET";
		$body = 'php://memory';
		$headers = [];

		return new \Laminas\Diactoros\ServerRequest(
			$serverParams,
			$fileParams,
			$targetUrl,
			$method,
			$body,
			$headers
		);
	}
	
	private function checkReadAccess($targetUrl) {
		// split out $targetUrl into $userId and $path https://nextcloud.server/solid/@alice/storage/foo/bar
		// - userId in this case is the pod owner (not the one doing the request). (alice)
		// - path is the path within the storage pod (/foo/bar)
		$target = $this->parseTargetUrl($targetUrl);
		$userId = $target["userId"];
		$path = $target["path"];
		
		$this->initializeStorage($userId);
		$this->WAC = new WAC($this->filesystem);

		$baseUrl = $this->getStorageUrl($userId);
		$this->WAC->setBaseUrl($baseUrl);

		$serverParams = [];
		$fileParams = [];

		$request = $this->createGetRequest($targetUrl);
		if (!$this->WAC->isAllowed($request, $this->webId)) { // Deny if we don't have read grants on the URL;
			return false;
		}
		return true;
	}
}
