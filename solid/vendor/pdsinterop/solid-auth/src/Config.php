<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth;

use Pdsinterop\Solid\Auth\Config\Client;
use Pdsinterop\Solid\Auth\Config\Expiration;
use Pdsinterop\Solid\Auth\Config\KeysInterface as Keys;
use Pdsinterop\Solid\Auth\Config\ServerInterface as Server;

class Config
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var Client */
    private $client;
    /** @var Expiration */
    private $expiration;
    /** @var array */
    private $grantTypes;
    /**@var Keys */
    private $keys;
    /** @var Server */
    private $server;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @return Client */
    public function getClient() : Client
    {
        return $this->client;
    }

    public function getExpiration() : Expiration
    {
        return $this->expiration;
    }

    public function getGrantTypes() : array
    {
        return $this->grantTypes;
    }

    public function getKeys() : Keys
    {
        return $this->keys;
    }

    public function getServer() : Server
    {
        return $this->server;
    }


    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(Client $client, Expiration $expiration, array $grantTypes, Keys $keys, Server $server)
    {
        $this->client = $client;
        $this->expiration = $expiration;
        $this->grantTypes = $grantTypes;
        $this->keys = $keys;
        $this->server = $server;
    }
}
