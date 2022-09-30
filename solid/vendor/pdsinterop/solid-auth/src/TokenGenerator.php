<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth;

use Pdsinterop\Solid\Auth\Exception\InvalidTokenException;
use Pdsinterop\Solid\Auth\Utils\DPop;
use Pdsinterop\Solid\Auth\Enum\OpenId\OpenIdConnectMetadata as OidcMeta;
use Laminas\Diactoros\Response\JsonResponse;
use League\OAuth2\Server\CryptTrait;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class TokenGenerator
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    use CryptTrait;

    public Config $config;

    private \DateInterval $validFor;
    private DPop $dpopUtil;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(
        Config $config,
        \DateInterval $validFor,
        DPop $dpopUtil,
    ) {
        $this->config = $config;
        $this->dpopUtil = $dpopUtil;
        $this->validFor = $validFor;

        $this->setEncryptionKey($this->config->getKeys()->getEncryptionKey());
    }

	public function generateRegistrationAccessToken($clientId, $privateKey) {
		$issuer = $this->config->getServer()->get(OidcMeta::ISSUER);

		// Create JWT
		$jwtConfig = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($privateKey));
		$token = $jwtConfig->builder()
			->issuedBy($issuer)
			->permittedFor($clientId)
			->relatedTo($clientId)
			->getToken($jwtConfig->signer(), $jwtConfig->signingKey());

		return $token->toString();
	}

    /**
     * Please note that the DPOP _is not_ required when requesting a token to
     * authorize a client but the DPOP _is_ required when requesting an access
     * token.
     */
	public function generateIdToken($accessToken, $clientId, $subject, $nonce, $privateKey, $dpop=null, $now=null) {
		$issuer = $this->config->getServer()->get(OidcMeta::ISSUER);

		$tokenHash = $this->generateTokenHash($accessToken);

		// Create JWT
		$jwtConfig = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($privateKey));
		$now = $now ?? new DateTimeImmutable();
		$useAfter = $now->sub(new \DateInterval('PT1S'));

		$expire = $now->add($this->validFor);

		$token = $jwtConfig->builder()
			->issuedBy($issuer)
			->permittedFor($clientId)
			->issuedAt($now)
			->canOnlyBeUsedAfter($useAfter)
			->expiresAt($expire)
			->withClaim("azp", $clientId)
			->relatedTo($subject)
			->identifiedBy($this->generateJti())
			->withClaim("nonce", $nonce)
			->withClaim("at_hash", $tokenHash) //FIXME: at_hash should only be added if the response_type is a token
			->withClaim("c_hash", $tokenHash) // FIXME: c_hash should only be added if the response_type is a code
		;

		if ($dpop !== null) {
			$jkt = $this->makeJwkThumbprint($dpop);
			$token = $token->withClaim("cnf", [
				"jkt" => $jkt,
			]);
		}

		return $token->getToken($jwtConfig->signer(), $jwtConfig->signingKey())->toString();
	}

	public function respondToRegistration($registration, $privateKey) {
		/*
			Expects in $registration:
			client_id
			client_id_issued_at
			redirect_uris
			registration_client_uri
		*/
		$registration_access_token = $this->generateRegistrationAccessToken($registration['client_id'], $privateKey);

		$registrationBase = array(
			'response_types' => array("id_token token"),
			'grant_types' => array("implicit"),
			'application_type' => 'web',
			'id_token_signed_response_alg' => "RS256",
			'token_endpoint_auth_method' => 'client_secret_basic',
			'registration_access_token' => $registration_access_token,
		);

		return array_merge($registrationBase, $registration);
	}

	public function addIdTokenToResponse($response, $clientId, $subject, $nonce, $privateKey, $dpop=null) {
		if ($response->hasHeader("Location")) {
			$value = $response->getHeaderLine("Location");

			if (preg_match("/#access_token=(.*?)&/", $value, $matches)) {
				$idToken = $this->generateIdToken(
					$matches[1],
					$clientId,
					$subject,
					$nonce,
					$privateKey,
					$dpop
				);
				$value = preg_replace("/#access_token=(.*?)&/", "#access_token=\$1&id_token=$idToken&", $value);
				$response = $response->withHeader("Location", $value);
			} else if (preg_match("/code=(.*?)&/", $value, $matches)) {
				$idToken = $this->generateIdToken(
					$matches[1],
					$clientId,
					$subject,
					$nonce,
					$privateKey,
					$dpop
				);
				$value = preg_replace("/code=(.*?)&/", "code=\$1&id_token=$idToken&", $value);
				$response = $response->withHeader("Location", $value);
			}
		} else {
			$response->getBody()->rewind();
			$responseBody = $response->getBody()->getContents();
			try {
				$body = json_decode($responseBody, true);
				if (isset($body['access_token'])) {
					$body['id_token'] = $this->generateIdToken(
						$body['access_token'],
						$clientId,
						$subject,
						$nonce,
						$privateKey,
						$dpop
					);

					$body['access_token'] = $body['id_token'];
					return new JsonResponse($body);
				}
			} catch (\Exception $e) {
				// leave the response as it was;
			}
		}
		return $response;
	}

	public function getCodeInfo($code) {
		return json_decode($this->decrypt($code), true);
	}

	///////////////////////////// HELPER FUNCTIONS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

	private function generateJti() {
		return substr(md5((string)time()), 12); // FIXME: generate unique jti values
	}

	private function generateTokenHash($accessToken) {
		$atHash = hash('sha256', $accessToken);
		$atHash = substr($atHash, 0, 32);
		$atHash = hex2bin($atHash);
		$atHash = base64_encode($atHash);
		$atHash = rtrim($atHash, '=');
		$atHash = str_replace('/', '_', $atHash);
		$atHash = str_replace('+', '-', $atHash);

		return $atHash;
	}

	private function makeJwkThumbprint($dpop): string
	{
		$dpopConfig = Configuration::forUnsecuredSigner();
		$parsedDpop = $dpopConfig->parser()->parse($dpop);
		$jwk = $parsedDpop->headers()->get("jwk");

		if (empty($jwk)) {
			throw new InvalidTokenException('Required JWK header missing in DPOP');
		}

		return $this->dpopUtil->makeJwkThumbprint($jwk);
	}
}
