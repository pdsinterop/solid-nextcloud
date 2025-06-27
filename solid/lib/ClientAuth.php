<?php
	namespace OCA\Solid;

	use OCP\User\Backend\ABackend;
	use OCP\User\Backend\ICheckPasswordBackend;
	
	/**
	 * @package OCA\Solid
	 */
	class ClientAuth extends ABackend implements ICheckPasswordBackend {
		public function __construct() {
			error_log("SO Constructed solid client auth backend");
		}

		public function checkPassword(string $username, string $password) {
			error_log("SO checking password for $username");
			return true;
		}

		public function getBackendName() {
			error_log("SO getBackendName");
			return "Solid";
		}
		public function deleteUser($uid) {
			error_log("SO deleteUser");
			return false;
		}
		public function getUsers($search = "", $limit = null, $offset = null, $callback = null) {
			error_log("SO getUsers");
			return [];
		}
		public function userExists($uid) {
			error_log("SO User exists");
			return true;
		}
		public function getDisplayName($uid) {
			error_log("SO getDisplayName");
			return "Solid client";
		}
		public function getDisplayNames($search = "", $limit = null, $offset = null) {
			error_log("SO getDisplayNames");
			return [];
		}
		public function hasUserListings() {
			error_log("SO hasUserListings");
			return false;
		}
    	}
