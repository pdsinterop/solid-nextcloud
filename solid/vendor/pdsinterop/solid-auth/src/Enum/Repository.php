<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum;

use Pdsinterop\Solid\Auth\Repository\AccessToken;
use Pdsinterop\Solid\Auth\Repository\AuthCode;
use Pdsinterop\Solid\Auth\Repository\Client;
use Pdsinterop\Solid\Auth\Repository\RefreshToken;
use Pdsinterop\Solid\Auth\Repository\Scope;

class Repository
{
    public const ACCESS_TOKEN = AccessToken::class;
    public const AUTH_CODE = AuthCode::class;
    public const CLIENT = Client::class;
    public const REFRESH_TOKEN = RefreshToken::class;
    public const SCOPE = Scope::class;
    // The USER class is only used by the deprecated password grant
    // public const USER = \Pdsinterop\Solid\Auth\Repository\User::class;
}
