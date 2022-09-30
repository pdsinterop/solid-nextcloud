<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Factory;

use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\CryptKey;
use Pdsinterop\Solid\Auth\Config;
use Pdsinterop\Solid\Auth\Enum\OAuth2\GrantType;
use Pdsinterop\Solid\Auth\Enum\Time;

class ConfigFactory
{
    /** @var Config\Client */
    private $client;
    /** @var string */
    private $encryptionKey;
    /** @var string */
    private $privateKey;
    /** @var string */
    private $publicKey;
    /** @var array */
    private $serverConfig;

    final public function __construct(
        Config\Client $client,
        string $encryptionKey,
        string $privateKey,
        string $publicKey,
        array $serverConfig
    ) {
        $this->client = $client;
        $this->encryptionKey = $encryptionKey;
        $this->privateKey = $privateKey;
        $this->serverConfig = $serverConfig;
        $this->publicKey = $publicKey;
    }

    final public function create() : Config
    {
        $client = $this->client;
        $encryptionKey = $this->encryptionKey;
        $privateKey = $this->privateKey;
        $publicKey = $this->publicKey;

        $expiration = new Config\Expiration(Time::HOURS_1, Time::MINUTES_10, Time::MONTHS_1);

        $grantTypes = [
            GrantType::AUTH_CODE,
            GrantType::CLIENT_CREDENTIALS,
            GrantType::IMPLICIT,
            GrantType::REFRESH_TOKEN,
        ];

        $keys = new Config\Keys(
            new CryptKey($privateKey),
            InMemory::plainText($publicKey),
            $encryptionKey
        );

        $server = new Config\Server($this->serverConfig);

        return new Config(
            $client,
            $expiration,
            $grantTypes,
            $keys,
            $server
        );
    }
}
