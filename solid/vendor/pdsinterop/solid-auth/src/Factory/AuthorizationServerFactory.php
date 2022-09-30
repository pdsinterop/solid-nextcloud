<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Factory;

use League\OAuth2\Server\AuthorizationServer;
use Pdsinterop\Solid\Auth\Config;
use Pdsinterop\Solid\Auth\Enum\Repository;
use Pdsinterop\Solid\Auth\Repository\Client;

class AuthorizationServerFactory
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var Config */
    private $config;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(Config $config)
    {
        $this->config = $config;
    }

    final public function create() : AuthorizationServer
    {
        $config = $this->config;

        $client = $config->getClient();
        $expiration = $config->getExpiration();
        $grantTypes = $config->getGrantTypes();
        $keys = $config->getKeys();

        $repositoryFactory = new RepositoryFactory([
            Repository::CLIENT => new Client(
                $client->getIdentifier(),
                $client->getSecret(),
                $client->getName(),
                $grantTypes,
                $client->getRedirectUris()
            ),
        ]);

        $server = new AuthorizationServer(
            $repositoryFactory->createClientRepository(),
            $repositoryFactory->createAccessTokenRepository(),
            $repositoryFactory->createScopeRepository(),
            $keys->getPrivateKey(),
            $keys->getEncryptionKey()
        );

        $grantTypeFactory = new GrantTypeFactory($expiration, $repositoryFactory);

        array_walk($grantTypes, static function ($grantType) use ($expiration, $grantTypeFactory, $server) {
            $grant = $grantTypeFactory->createGrantType($grantType);

            $server->enableGrantType(
                $grant,
                $expiration->forAccessToken()
            );
        });

        return $server;
    }
}
