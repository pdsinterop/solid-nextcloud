<?php

declare(strict_types=1);

namespace OCA\Solid\AppInfo;

use OC;
use OC\AppConfig;

use OCA\Solid\Service\SolidWebhookService;
use OCA\Solid\Db\SolidWebhookMapper;
use OCA\Solid\Middleware\SolidCorsMiddleware;
use OCA\Solid\ClientAuth;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Server;

class Application extends App implements IBootstrap {
    public const APP_ID = 'solid';
    public static $userSubDomainsEnabled;

    /**
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        $request = \OCP\Server::get(\OCP\IRequest::class);
        $rawPathInfo = $request->getRawPathInfo();
        error_log($rawPathInfo);

        if ($rawPathInfo == '/apps/solid/token') {
            $backend = new \OCA\Solid\ClientAuth();
            \OC::$server->getUserManager()->registerBackend($backend);
        }
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerWellKnownHandler(\OCA\Solid\WellKnown\OpenIdConfigurationHandler::class);
        $context->registerWellKnownHandler(\OCA\Solid\WellKnown\SolidHandler::class);
        $context->registerMiddleware(SolidCorsMiddleware::class);

        /**
         * Core class wrappers
         */

        $context->registerService('UserService', function($c) {
            return new \OCA\Solid\Service\UserService(
                $c->query('UserSession')
            );
        });
        $context->registerService('UserSession', function($c) {
            return $c->query('ServerContainer')->getUserSession();
        });

        // currently logged in user, userId can be gotten by calling the
        // getUID() method on it
        $context->registerService('User', function($c) {
            return $c->query('UserSession')->getUser();
        });

        /* webhook DB services */
        $context->registerService(SolidWebhookService::class, function($c): SolidWebhookService {
            return new SolidWebhookService(
                $c->query(SolidWebhookMapper::class)
            );
        });

        $context->registerService(SolidWebhookMapper::class, function($c): SolidWebhookMapper {
            return new SolidWebhookMapper(
                $c->get(\OCP\IDBConnection::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        self::$userSubDomainsEnabled = OC::$server->get(AppConfig::class)->getValueBool(self::APP_ID, 'userSubDomainsEnabled');
        require_once(__DIR__.'/../../vendor/autoload.php');
    }
}
