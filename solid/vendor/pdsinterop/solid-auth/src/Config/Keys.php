<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Config;

use Defuse\Crypto\Key as CryptoKey;
use Lcobucci\JWT\Signer\Key\InMemory as Key;
use League\OAuth2\Server\CryptKey;

class Keys implements KeysInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private string|CryptoKey $encryptionKey;
    private CryptKey $privateKey;
    private Key $publicKey;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getEncryptionKey(): CryptoKey|string
    {
        return $this->encryptionKey;
    }

    final public function getPrivateKey(): CryptKey
    {
        return $this->privateKey;
    }

    public function getPublicKey(): Key
    {
        return $this->publicKey;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(CryptKey $privateKey, Key $publicKey, CryptoKey|string $encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }
}
