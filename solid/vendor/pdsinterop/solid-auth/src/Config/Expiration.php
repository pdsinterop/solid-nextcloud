<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Config;

use DateInterval;

class Expiration
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var string */
    private $accessToken;
    /** @var string */
    private $authCode;
    /** @var string */
    private $refreshToken;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function forAccessToken() : DateInterval
    {
        return new DateInterval($this->accessToken);
    }

    public function forAuthCode() : DateInterval
    {
        return new DateInterval($this->authCode);
    }

    public function forRefreshToken() : DateInterval
    {
        return new DateInterval($this->refreshToken);
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function __construct(string $accessTokenExpires, string $authCodeExpires, string $refreshTokenExpires)
    {
        $this->accessToken = $accessTokenExpires;
        $this->authCode = $authCodeExpires;
        $this->refreshToken = $refreshTokenExpires;
    }
}
