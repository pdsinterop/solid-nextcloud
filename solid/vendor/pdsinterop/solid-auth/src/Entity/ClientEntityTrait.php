<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;

trait ClientEntityTrait
{
    /** @var ClientEntityInterface */
    private $clientEntity;

    public function __construct(ClientEntityInterface $clientEntity)
    {
        $this->setClientEntity($clientEntity);
    }

    public function getClientEntity() : ClientEntityInterface
    {
        if (method_exists($this, 'getClient')) {
            $clientEntity = $this->getClient();
        } else {
            $clientEntity = $this->clientEntity;
        }

        return $clientEntity;
    }

    public function setClientEntity(ClientEntityInterface $client) : void
    {
        if (method_exists($this, 'setClient')) {
            $this->setClient($client);
        } else {
            $this->clientEntity = $client;
        }

    }
}
