<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class Client implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    /**
     * Client constructor.
     *
     * @param string|null $identifier
     * @param string|null $name
     * @param string[]|null $redirectUri
     * @param bool $isConfidential
     */
    public function __construct(
        string $identifier = null,
        string $name = null,
        array $redirectUri = null,
        bool $isConfidential = false
    ) {
        $this->isConfidential = $isConfidential;
        $this->name = $name;
        $this->redirectUri = $redirectUri;
        $this->setIdentifier($identifier);
    }
}
