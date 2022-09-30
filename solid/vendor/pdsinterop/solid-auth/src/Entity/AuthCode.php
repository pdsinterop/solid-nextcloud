<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AuthCode implements AuthCodeEntityInterface
{
    /*/ League\OAuth2\Server Traits /*/
    use AuthCodeTrait;
    use EntityTrait;
    use TokenEntityTrait;

    /*/ Pdsinterop\Solid\Auth Traits /*/
    use ClientEntityTrait;
}
