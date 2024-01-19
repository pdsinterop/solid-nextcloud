<?php

namespace OCA\Solid\Service;

class UserService {
	private $userSession;

	public function __construct($userSession) {
		$this->userSession = $userSession;
	}

	public function login($userId, $password) {
		return $this->userSession->login($userId, $password);
	}

	public function logout() {
		$this->userSession->logout();
	}
}
