<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Config;

class Client
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var string */
    private $identifier;
    /** @var string */
    private $name;
    /** @var array */
    private $redirectUris;
    /** @var string */
    private $secret;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getIdentifier() : string
    {
        return $this->identifier;
    }

    final public function getName() : string
    {
        return $this->name;
    }

    final public function getRedirectUris() : array
    {
        return $this->redirectUris;
    }

    final public function getSecret() : string
    {
        return $this->secret;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(
        string $identifier,
        string $secret,
        array $redirectUris,
        string $name = ''
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUris = $redirectUris;
        $this->secret = $secret;
    }
}
