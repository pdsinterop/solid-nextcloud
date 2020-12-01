/*
SPDX-FileCopyrightText: 2020, Michiel de Jong <<michiel@unhosted.org>>
*
SPDX-License-Identifier: MIT
*/



<?php
namespace OCA\Solid\Service;

class UserService {

    private $userSession;

    public function __construct($userSession){
        $this->userSession = $userSession;
    }

    public function login($userId, $password) {
        return $this->userSession->login($userId, $password);
    }

    public function logout() {
        $this->userSession->logout();
    }

}
