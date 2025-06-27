<?php
	/* 
		IMPORTANT WARNING!

		This class is a user backend that accepts 'all'.
		Any user, and password is currently accepted as true.
		
		The reason this is here is that Solid clients will use basic
		authentication to do a POST request to the token endpoint,
		where the actual authorization happens.

		The security for this user backend lies in the fact that it
		is only activated for the token endpoint in the Solid app. 

		In /lib/AppInfo/Application.php there is a check for the
		token endpoint before this thing activates.
				
		It is completely unsuitable as an actual user backend in the
		normal sense of the word.
		
		It is here to allow the token requests with basic
		authentication requests to pass to us.
	*/

	namespace OCA\Solid;

	use OCP\User\Backend\ABackend;
	use OCP\User\Backend\ICheckPasswordBackend;
	
	/**
	 * @package OCA\Solid
	 */
	class ClientAuth extends ABackend implements ICheckPasswordBackend {
		public function __construct() {
		}

		public function checkPassword(string $username, string $password) {
			return true;
		}

		public function getBackendName() {
			return "Solid";
		}
		public function deleteUser($uid) {
			return false;
		}
		public function getUsers($search = "", $limit = null, $offset = null, $callback = null) {
			return [];
		}
		public function userExists($uid) {
			return true;
		}
		public function getDisplayName($uid) {
			return "Solid client";
		}
		public function getDisplayNames($search = "", $limit = null, $offset = null) {
			return [];
		}
		public function hasUserListings() {
			return false;
		}
	}
