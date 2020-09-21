<?php
namespace OCA\Solid\AppInfo;

use \OCP\AppFramework\App;

use \OCA\Solid\Service\UserService;


class SolidApp extends App {

	public const APP_ID = 'solid';

    public function __construct(){
        parent::__construct(self::APP_ID);

        $container = $this->getContainer();
error_log('a');
        $container->registerService('UserService', function($c) {
            return new \OCA\Solid\Service\UserService(
                $c->query('UserSession')
            );
        });
error_log('b');
        $container->registerService('UserSession', function($c) {
            return $c->query('ServerContainer')->getUserSession();
        });

        // currently logged in user, userId can be gotten by calling the
        // getUID() method on it
        $container->registerService('User', function($c) {
            return $c->query('UserSession')->getUser();
        });

    }

}
	