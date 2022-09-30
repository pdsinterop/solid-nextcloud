<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Pdsinterop\Solid\Auth\Entity\Scope as ScopeEntity;

class Scope implements ScopeRepositoryInterface
{
    /**
     * Return information about a scope.
     *
     * @param string $identifier The scope identifier
     *
     * @return ScopeEntityInterface|null
     */
    public function getScopeEntityByIdentifier($identifier) : ?ScopeEntityInterface
    {
        /*/
            This method is called to validate a scope.

            If the scope is valid you should return an instance of \League\OAuth2\Server\Entities\Interfaces\ScopeEntityInterface
        /*/
        $scope = new ScopeEntity();
        $scope->setIdentifier($identifier);

        return $scope;
    }

    /**
     * Given a client, grant type and optional user identifier validate the set of scopes requested are valid and optionally
     * append additional scopes or remove requested scopes.
     *
     * @param ScopeEntityInterface[] $scopes
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @param null|string $userIdentifier
     *
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) : array {
        /*/
            This method is called right before an access token or authorization code is created.

            Given a client, grant type and optional user identifier validate the set of scopes requested are valid and
            optionally append additional scopes or remove requested scopes.

            This method is useful for integrating with your own app’s permissions system.

            You must return an array of ScopeEntityInterface instances; either the original scopes or an updated set.
        /*/
        return $scopes;
    }
}
