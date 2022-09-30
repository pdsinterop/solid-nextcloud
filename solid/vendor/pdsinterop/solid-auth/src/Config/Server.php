<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Config;

use JsonSerializable;
use Pdsinterop\Solid\Auth\Enum\OpenId\OpenIdConnectMetadata as OidcMeta;
use Pdsinterop\Solid\Auth\Exception\LogicException;

class Server implements ServerInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @var array */
    private $data;
    /** @var bool */
    private $strict;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function get($key)
    {
        return $this->data[$key] ?: null;
    }

    private function getRecommended() : array
    {
        return [
            OidcMeta::CLAIMS_SUPPORTED,
            OidcMeta::REGISTRATION_ENDPOINT,
            OidcMeta::SCOPES_SUPPORTED,
            OidcMeta::USERINFO_ENDPOINT,
        ];
    }

    final public function getRequired() : array
    {
        $required = [
            OidcMeta::AUTHORIZATION_ENDPOINT,
            OidcMeta::ID_TOKEN_SIGNING_ALG_VALUES_SUPPORTED,
            OidcMeta::ISSUER,
            OidcMeta::JWKS_URI,
            OidcMeta::RESPONSE_TYPES_SUPPORTED,
            OidcMeta::SUBJECT_TYPES_SUPPORTED,
        ];

        if ($this->strict === true) {
            $required = array_merge($required, $this->getRecommended());
        }

        return $required;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(array $data, bool $strict = false)
    {
        $data = array_merge([
            OidcMeta::ID_TOKEN_SIGNING_ALG_VALUES_SUPPORTED => ['RS256'],
            OidcMeta::SUBJECT_TYPES_SUPPORTED =>  ['public'],
			OidcMeta::RESPONSE_TYPES_SUPPORTED => array("code","code token","code id_token","id_token code","id_token","id_token token","code id_token token","none"),
			OidcMeta::TOKEN_TYPES_SUPPORTED => array("legacyPop","dpop"),
			OidcMeta::RESPONSE_MODES_SUPPORTED => array("query","fragment"),
			OidcMeta::GRANT_TYPES_SUPPORTED => array("authorization_code","implicit","refresh_token","client_credentials"),
			OidcMeta::TOKEN_ENDPOINT_AUTH_METHODS_SUPPORTED => "client_secret_basic",
			OidcMeta::TOKEN_ENDPOINT_AUTH_SIGNING_ALG_VALUES_SUPPORTED => ["RS256"],
			OidcMeta::CODE_CHALLENGE_METHODS_SUPPORTED => ["S256"],
			OidcMeta::DPOP_SIGNING_ALG_VALUES_SUPPORTED => ["RS256"],
			OidcMeta::DISPLAY_VALUES_SUPPORTED => [],
			OidcMeta::CLAIM_TYPES_SUPPORTED => ["normal"],
			OidcMeta::CLAIMS_SUPPORTED => ["webid"],
			OidcMeta::SCOPES_SUPPORTED => ["webid"],
			OidcMeta::CLAIMS_PARAMETER_SUPPORTED => false,
			OidcMeta::REQUEST_PARAMETER_SUPPORTED => true,
			OidcMeta::REQUEST_URI_PARAMETER_SUPPORTED => false,
			OidcMeta::REQUIRE_REQUEST_URI_REGISTRATION => false
		], $data);

        $this->data = array_filter($data, [OidcMeta::class, 'has'], ARRAY_FILTER_USE_KEY);
        $this->strict = $strict;
    }

    final public function __toString() : string
    {
        return (string) json_encode($this);
    }

    /**
     * @return array
     *
     * @throws LogicException for missing required properties
     */
    final public function jsonSerialize() : array
    {
        $data = $this->data;

        if ($this->validate() === false) {
            $missing = $this->checkForMissing($data, $this->getRequired());
            throw new LogicException('Required properties have not been set: ' . implode(', ', $missing));
        }

        return $data;
    }

    final public function validate() : bool
    {
        return $this->checkForMissing($this->data, $this->getRequired()) === [];
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function checkForMissing(array $data, array $required) : array
    {
        $available = array_keys($data);

        return array_diff($required, $available);
    }
}
