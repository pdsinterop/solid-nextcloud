<?php
namespace OCA\Solid\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;


class Conf implements JsonSerializable {
        public function jsonSerialize() {
          return [ 'hello' => 'world' ];
        }
}

class OpenidConfigurationController extends Controller {

	public function __construct(string $AppName, IRequest $request){
		parent::__construct($AppName, $request);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
               conf = new Conf();
               return new DataResponse(conf); 
		// readfile('openid.json');
	}
}
