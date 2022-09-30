<?php

namespace Pdsinterop\Solid\Auth\Config;

use Defuse\Crypto\Key as CryptoKey;
use Lcobucci\JWT\Signer\Key\InMemory as Key;
use League\OAuth2\Server\CryptKey;

interface KeysInterface
{
    public function getEncryptionKey(): CryptoKey|string;

    public function getPrivateKey(): CryptKey;

    public function getPublicKey(): Key;
}
