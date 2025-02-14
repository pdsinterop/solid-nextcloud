<?php

namespace OCA\Solid\Controller;

use Closure;
use OCA\Solid\AppInfo\Application;
use OCA\Solid\Service\SolidWebhookService;
use OCA\Solid\ServerConfig;
use OCA\Solid\PlainResponse;
use OCA\Solid\Notifications\SolidNotifications;
use OCA\Solid\DpopFactoryTrait;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IDBConnection;
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
	use DpopFactoryTrait;

	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	/** @var SolidWebhookService */
	private $webhookService;

	public function __construct(
		$AppName,
		IRootFolder $rootFolder,
		IRequest $request,
		ISession $session,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		$userId,
		IConfig $config,
		SolidWebhookService $webhookService,
		IDBConnection $connection,
	) {
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\Solid\ServerConfig($config, $urlGenerator, $userManager);
		$this->rootFolder = $rootFolder;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
		$this->webhookService = $webhookService;

		$this->setJtiStorage($connection);
		$this->DPop = $this->getDpop();
		try {
			$this->rawRequest = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
			$this->webId = $this->DPop->getWebId($this->rawRequest);
			// FIXME: Should we handle webhooks for 'public'?
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
	public function registerWs(string $topic): DataResponse {
		$toSub = "http://pubsub:8081";
		$toPub = "http://pubsub:8082";
		// FIXME: is this secure enough?
		// https://www.php.net/manual/en/function.random-bytes.php says it
		// generates "cryptographically secure pseudo-random bytes"
		$token = bin2hex(random_bytes(20));
		$target = "$toPub/$token";
		if ($this->checkReadAccess($topic)) {
			$webhook = $this->webhookService->create($this->webId, $topic, $target);
			return new DataResponse([
				"@context" => "https://www.w3.org/ns/solid/notification/v1",
				"type" => "WebSocketSubscription2021",
				"source" => "$toSub/$token"
			]);
		} else {
			return new DataResponse("Error: denied access", 401);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function register(string $topic, string $target): DataResponse {
		if (!$this->isValidWebhookTarget($target)) {
			return new DataResponse("Error: invalid webhook target", 422);
		}

		if ($this->checkReadAccess($topic)) {
			return new DataResponse($this->webhookService->create($this->webId, $topic, $target));
		} else {
			return new DataResponse("Error: denied access", 401);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function unregister(string $topic): DataResponse {
		return $this->handleNotFound(function () use ($topic) {
			return $this->webhookService->delete($this->webId, $topic);
		});
	}

	private function isValidWebhookTarget($target) {
		if (!preg_match("|^https://|", $target)) {
			return false;
		}
		return true;
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

	private function parseTopic($topic) {
		// topic = https://nextcloud.server/solid/@alice/storage/foo/bar
		$appBaseUrl = $this->getAppBaseUrl(); //  https://nextcloud.server/solid/
		$internalUrl = str_replace($appBaseUrl, '', $topic); // @alice/storage/foo/bar
		$pathicles = explode("/", $internalUrl);
		$userId = $pathicles[0]; // @alice
		$userId = preg_replace("/^@/", "", $userId); // alice
                $storageUrl = $this->getStorageUrl($userId); // https://nextcloud.server/solid/@alice/storage/
		$storagePath = str_replace($storageUrl, '/', $topic); // /foo/bar
		return array(
			"userId" => $userId,
			"path" => $storagePath
		);
	}
	
	private function createGetRequest($topic) {
		$serverParams = [];
		$fileParams = [];
		$method = "GET";
		$body = 'php://memory';
		$headers = [];

		return new \Laminas\Diactoros\ServerRequest(
			$serverParams,
			$fileParams,
			$topic,
			$method,
			$body,
			$headers
		);
	}
	
	private function checkReadAccess($topic) {
		// split out $topic into $userId and $path https://nextcloud.server/solid/@alice/storage/foo/bar
		// - userId in this case is the pod owner (not the one doing the request). (alice)
		// - path is the path within the storage pod (/foo/bar)
		$target = $this->parseTopic($topic);
		$userId = $target["userId"];
		$path = $target["path"];
		
		$this->initializeStorage($userId);
		$this->WAC = new WAC($this->filesystem);

		$baseUrl = $this->getStorageUrl($userId);
		$this->WAC->setBaseUrl($baseUrl);

		$serverParams = [];
		$fileParams = [];

		$request = $this->createGetRequest($topic);
		if (!$this->WAC->isAllowed($request, $this->webId)) { // Deny if we don't have read grants on the URL;
			return false;
		}
		return true;
	}

	private function handleNotFound(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (SolidWebhookNotFound $e) {
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
