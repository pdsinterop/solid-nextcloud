<?php
namespace OCA\Solid\Service;

use OCP\IUserSession;

class UserService {
    private IUserSession $userSession;

    public function __construct(IUserSession $userSession){
        $this->userSession = $userSession;
    }

    public function login($userId, $password) {
        return $this->userSession->login($userId, $password);
    }

    public function logout() {
        $this->userSession->logout();
    }
}
