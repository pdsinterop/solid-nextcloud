<?php
namespace OCA\Solid\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {
	private $userId;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
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

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function profile() {
		return new TemplateResponse('solid', 'profile');
	}


	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function turtleProfile() {
		return new TemplateResponse('solid', 'turtle-profile', [], 'blank');
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function openid() {
		return(' { "issuer":"https://solid.community", "jwks_uri":"https://solid.community/jwks", "response_types_supported":[ "code", "code token", "code id_token", "id_token code", "id_token", "id_token token", "code id_token token", "none" ], "token_types_supported":[ "legacyPop", "dpop" ], "response_modes_supported":[ "query", "fragment" ], "grant_types_supported":[ "authorization_code", "implicit", "refresh_token", "client_credentials" ], "subject_types_supported":["public"], "id_token_signing_alg_values_supported":["RS256"], "token_endpoint_auth_methods_supported":"client_secret_basic", "token_endpoint_auth_signing_alg_values_supported":["RS256"], "display_values_supported":[], "claim_types_supported":["normal"], "claims_supported":[], "claims_parameter_supported":false, "request_parameter_supported":true, "request_uri_parameter_supported":false, "require_request_uri_registration":false, "check_session_iframe":"https://solid.community/session", "end_session_endpoint":"https://solid.community/logout", "authorization_endpoint":"https://solid.community/authorize", "token_endpoint":"https://solid.community/token", "userinfo_endpoint":"https://solid.community/userinfo", "registration_endpoint":"https://solid.community/register" } ');
	}
}
