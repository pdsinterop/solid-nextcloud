<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Repository;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Pdsinterop\Solid\Auth\Entity\RefreshToken as RefreshTokenEntity;

class RefreshToken implements RefreshTokenRepositoryInterface
{
    /**
     * Creates a new refresh token
     *
     * @return RefreshTokenEntityInterface|null
     */
    public function getNewRefreshToken() : ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * Called when a new refresh token is created
     *
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity) : void
    {
        // throw new UniqueTokenIdentifierConstraintViolationException;
        /*/
            When a new refresh token is created this method will be called. You don’t have to do anything here but for
            auditing you might want to.

            The refresh token entity passed in has a number of methods you can call which contain data worth saving to
            a database:

                getIdentifier() : string this is randomly generated unique identifier (of 80+ characters in length) for the refresh token.
                getExpiryDateTime() : \DateTime the expiry date and time of the refresh token.
                getAccessToken()->getIdentifier() : string the linked access token’s identifier.

            JWT access tokens contain an expiry date and so will be rejected automatically when used. You can safely
            clean up expired access tokens from your database.
        /*/
    }

    /**
     * Revoke the refresh token.
     *
     * @param string $tokenId
     */
    public function revokeRefreshToken($tokenId) : void
    {
        /*/
            This method is called when a refresh token is used to reissue an access token.

            The original refresh token is revoked a new refresh token is issued.
        /*/
    }

    /**
     * Check if the refresh token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isRefreshTokenRevoked($tokenId) : bool
    {
        /*/
            This method is called when an refresh token is used to issue a new access token.

            Return true if the refresh token has been manually revoked before it expired.
            If the token is still valid return false.
        /*/
        return false;
    }
}
