<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Repository;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Pdsinterop\Solid\Auth\Entity\AccessToken as AccessTokenEntity;

class AccessToken implements AccessTokenRepositoryInterface
{
    /**
     * Create a new access token
     *
     * @param ClientEntityInterface $clientEntity
     * @param ScopeEntityInterface[] $scopes
     * @param mixed $userIdentifier
     *
     * @return AccessTokenEntityInterface
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ) : AccessTokenEntityInterface {

		$accessToken = new AccessTokenEntity($clientEntity);
		$accessToken->setUserIdentifier($userIdentifier);
		foreach ($scopes as $scope) {
			$accessToken->addScope(new \Pdsinterop\Solid\Auth\Entity\Scope($scope));
		}
		return $accessToken;
    }

    /**
     * Persists a new access token to permanent storage.
     *
     * @param AccessTokenEntityInterface $accessTokenEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity) : void
    {
        // throw new UniqueTokenIdentifierConstraintViolationException()
        /*/
            When a new access token is created this method will be called. You don’t have to do anything here but for auditing you probably want to.

            The access token entity passed in has a number of methods you can call which contain data worth saving to a database:

                getIdentifier() : string this is randomly generated unique identifier (of 80+ characters in length) for the access token.
                getExpiryDateTime() : \DateTime the expiry date and time of the access token.
                getUserIdentifier() : string|null the user identifier represented by the access token.
                getScopes() : ScopeEntityInterface[] an array of scope entities
                getClient()->getIdentifier() : string the identifier of the client who requested the access token.

        JWT access tokens contain an expiry date and so will be rejected automatically when used. You can safely clean up expired access tokens from your database.
        /*/
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId) : void
    {
        /*/
            This method is called when a refresh token is used to reissue an access token.

            The original access token is revoked a new access token is issued.
        /*/
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isAccessTokenRevoked($tokenId) : bool
    {
        /*/
            This method is called when an access token is validated by the resource server middleware.

            Return true if the access token has been manually revoked before it expired.

            If the token is still valid return false.
        /*/
        return false;
    }
}
