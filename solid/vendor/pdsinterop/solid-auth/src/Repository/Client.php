<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Pdsinterop\Solid\Auth\Entity\Client as ClientEntity;

class Client implements ClientRepositoryInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var array */
    private $grantTypes;
    /** @var string */
    private $identifier;
    /** @var string */
    private $secret;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    /**
     * @var string
     */
    private $name;
    /**
     * @var array
     */
    private $redirectUri;

    public function __construct(
        string $identifier,
        string $secret = '',
        string $name = '',
        array $grants = [],
        array $redirectUri = []
    ) {
        $this->grantTypes = $grants;
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUri;
        $this->secret = $secret;
    }

    public function createClientEntity($identifier = null) : ClientEntityInterface
    {
        $client = new ClientEntity(
            $identifier,
            $this->name,
            $this->redirectUri,
            $this->secret !== ''
        );

        return $client;
    }

    /**
     * Get a client.
     *
     * @param mixed $identifier The client's identifier
     *
     * @return ClientEntityInterface|null
     */
    public function getClientEntity($identifier) : ?ClientEntityInterface
    {
        return $this->createClientEntity($identifier);
    }

    /**
     * Validate a client's secret.
     *
     * @param string $clientIdentifier The client's identifier
     * @param null|string $clientSecret The client's secret (if sent)
     * @param null|string $grantType The type of grant the client is using (if sent)
     *
     * @return bool
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType) : bool
    {
        /*/
            This method is called to validate a client’s credentials.

            The client secret may or may not be provided depending on the request sent by the client.

            If the client is confidential (i.e. is capable of securely storing a secret) then the secret must be validated.

            You can use the grant type to determine if the client is permitted to use the grant type.

            If the client’s credentials are validated you should return true, otherwise return false.
        /*/

        return $this->identifier === $clientIdentifier
            && ($this->secret === '' || $this->secret === $clientSecret)
            && ($this->grantTypes === [] || in_array($grantType, $this->grantTypes, true))
        ;
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
}
