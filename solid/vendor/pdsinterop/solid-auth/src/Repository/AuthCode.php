<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Repository;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Pdsinterop\Solid\Auth\Entity\AuthCode as AuthCodeEntity;
use Pdsinterop\Solid\Auth\Entity\ClientEntityTrait;

class AuthCode implements AuthCodeRepositoryInterface
{
    use ClientEntityTrait;

    /**
     * Creates a new AuthCode
     *
     * @return AuthCodeEntityInterface
     */
    public function getNewAuthCode() : AuthCodeEntityInterface
    {
        return new AuthCodeEntity($this->getClientEntity());
    }

    /**
     * Persists a new auth code to permanent storage.
     *
     * @param AuthCodeEntityInterface $authCodeEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity) : void
    {
        /*/
            When a new auth code is created this method will be called. You don’t
            have to do anything here but for auditing you probably want to.

            The auth code entity passed in has a number of methods you can call
            which contain data worth saving to a database:

                getIdentifier() : string this is randomly generated unique identifier (of 80+ characters in length) for the auth code.
                getExpiryDateTime() : \DateTime the expiry date and time of the auth code.
                getUserIdentifier() : string|null the user identifier represented by the auth code.
                getScopes() : ScopeEntityInterface[] an array of scope entities
                getClient()->getIdentifier() : string the identifier of the client who requested the auth code.

            The auth codes contain an expiry date and so will be rejected
            automatically if used when expired. You can safely clean up expired
            auth codes from your database.
        /*/
    }

    /**
     * Revoke an auth code.
     *
     * @param string $codeId
     */
    public function revokeAuthCode($codeId) : void
    {
        /*/
            This method is called when an authorization code is exchanged for an
            access token. You can also use it in your own business logic.
        /*/
    }

    /**
     * Check if the auth code has been revoked.
     *
     * @param string $codeId
     *
     * @return bool Return true if this code has been revoked
     */
    public function isAuthCodeRevoked($codeId) : bool
    {
        /*/
            This method is called before an authorization code is exchanged for an
            access token by the authorization server. Return true if the auth code
            has been manually revoked before it expired. If the auth code is still
            valid return false.
        /*/
        return false;
    }
}
