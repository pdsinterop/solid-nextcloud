<?php
namespace OCA\Solid\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class ServerController extends Controller {

	private $config;
	private $server;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$config = (new pdsinterop\Solid\Auth\Factory\ConfigFactory(
			'','', $encryptionKey, $privateKey
		));
		$server = 
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function openidConfiguration() {

	}	
}