<?php

namespace Pdsinterop\Solid\Auth\Utils;

use JsonSerializable;
use Lcobucci\JWT\Signer\Key\InMemory;
use Pdsinterop\Solid\Auth\Enum\Jwk\Parameter as JwkParameter;
use Pdsinterop\Solid\Auth\Enum\Rsa\Parameter as RsaParameter;

class Jwks implements JsonSerializable
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var InMemory */
    private $publicKey;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(InMemory $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    final public function __toString() : string
    {
        return (string) json_encode($this);
    }

    final public function jsonSerialize(): array
    {
        return $this->create();
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @param string $certificate
     * @param $subject
     *
     * @return array
     */
    private function createKey(string $certificate, $subject, $exponent) : array
    {
        return [
            JwkParameter::ALGORITHM => 'RS256',
            JwkParameter::KEY_ID => md5($certificate),
            JwkParameter::KEY_TYPE => 'RSA',
            RsaParameter::PUBLIC_EXPONENT => Base64Url::encode($exponent),
            RsaParameter::PUBLIC_MODULUS => Base64Url::encode($subject),
            JwkParameter::KEY_OPERATIONS => array("verify"),
        ];
    }

    /**
     * As the JWT library does not (yet?) have support for JWK, a custom solution is used for now.
     *
     * @return array
     *
     * @see https://github.com/lcobucci/jwt/issues/32
     */
    private function create() : array
    {
        $jwks = ['keys' => []];

        $publicKeys = [$this->publicKey];

        array_walk($publicKeys, function (InMemory $publicKey) use (&$jwks) {
            $certificate = $publicKey->contents();

            $key = openssl_pkey_get_public($certificate);
            $keyInfo = openssl_pkey_get_details($key);

            $jwks['keys'][] = $this->createKey($certificate, $keyInfo['rsa']['n'], $keyInfo['rsa']['e']);
        });

        return $jwks;
    }
}
