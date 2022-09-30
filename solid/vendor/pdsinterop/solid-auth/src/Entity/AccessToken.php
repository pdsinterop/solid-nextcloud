<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AccessToken implements AccessTokenEntityInterface
{
    /*/ League\OAuth2\Server Traits /*/
    use AccessTokenTrait;
    use EntityTrait;
    use TokenEntityTrait;

    /*/ Pdsinterop\Solid\Auth Traits /*/
    use ClientEntityTrait;
}
