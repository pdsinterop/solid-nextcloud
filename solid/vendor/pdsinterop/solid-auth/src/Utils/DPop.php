<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Utils;

use DateInterval;
use Exception;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\Core\Util\RSAKey;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Pdsinterop\Solid\Auth\Exception\AuthorizationHeaderException;
use Pdsinterop\Solid\Auth\Exception\InvalidTokenException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This class contains code to fetch the WebId from a request
 * It also verifies that the request has a valid DPoP token
 * that matches the access token
 */
class DPop {

    private JtiValidator $jtiValidator;

	public function __construct(JtiValidator $jtiValidator)
	{
		$this->jtiValidator = $jtiValidator;
	}

	/**
	 * This method fetches the WebId from a request and verifies
	 * that the request has a valid DPoP token that matches
	 * the access token.
	 *
	 * @param ServerRequestInterface $request Server Request
	 *
	 * @return string the WebId, or "public" if no WebId is found
	 *
	 * @throws Exception "Invalid token" when the DPoP token is invalid
	 * @throws Exception "Missing DPoP token" when the DPoP token is missing, but the Authorisation header in the request specifies it
	 */
	public function getWebId($request) {
		$serverParams = $request->getServerParams();

		if (empty($serverParams['HTTP_AUTHORIZATION'])) {
			$webId = "public";
		} else {
			$this->validateRequestHeaders($serverParams);

			[, $jwt] = explode(" ", $serverParams['HTTP_AUTHORIZATION'], 2);

			$dpop = $serverParams['HTTP_DPOP'];

			//@FIXME: check that there is just one DPoP token in the request
			try {
				$this->validateJwtDpop($jwt, $dpop, $request);
			} catch (RequiredConstraintsViolated $e) {
				throw new InvalidTokenException($e->getMessage(), 0, $e);
			}
			$webId = $this->getSubjectFromJwt($jwt);
		}

		return $webId;
	}

	/**
	 * kept for backwards compatability
	 * note: the "kid" value is not guaranteed to be a hash of the jwk
	 * so to compare a jkt, calculate the jwk thumbprint instead
	 * @param  string $dpop    The DPoP token, raw
	 * @param  ServerRequestInterface $request Server Request
	 * @return string          The "kid" from the "jwk" header
	 */
	public function getDpopKey($dpop, $request) {
		$this->validateDpop($dpop, $request);

		$jwtConfig = Configuration::forUnsecuredSigner();
		$dpop = $jwtConfig->parser()->parse($dpop);
		$jwk  = $dpop->headers()->get("jwk");

		if (isset($jwk['kid']) === false) {
			throw new InvalidTokenException('Key ID is missing from JWK header');
		}

		return $jwk['kid'];
	}

	/**
	 * RFC7638 defines a method for computing the hash value (or "digest") of a JSON Web Key (JWK).
	 *
	 * The resulting hash value can be used for identifying the key represented by the JWK
	 * that is the subject of the thumbprint.
	 *
	 * For instance by using the base64url-encoded JWK Thumbprint value as a key ID (or "kid") value.
	 *
	 * @see https://www.rfc-editor.org/rfc/rfc7638
	 *
	 * The thumbprint of a JWK is created by:
	 *
	 * 1. Constructing a JSON string (without whitespaces) with the required keys in alphabetical order.
	 * 2. Hashing the JSON string using SHA-256 (or another hash function)
	 *
	 * @param string $jwk The JWK key to thumbprint
	 * @return string the thumbprint
	 * @throws InvalidTokenException	 
	 */
	public function makeJwkThumbprint($jwk) {
		if (!$jwk || !isset($jwk['kty'])) {
			throw new InvalidTokenException('JWK has no "kty" key type');
		}
		// https://www.rfc-editor.org/rfc/rfc7517.html#section-4.1
		// and https://www.rfc-editor.org/rfc/rfc7518.html#section-6.1
		if (!in_array($jwk['kty'], ['RSA','EC'])) {
			throw new InvalidTokenException('JWK "kty" key type value must be one of "RSA" or "EC", got "'.$jwk['kty'].'" instead.');
		}
		if ($jwk['kty']=='RSA') { // used with RS256 alg
			if (!isset($jwk['e'], $jwk['n'])) {
				throw new InvalidTokenException('JWK values do not match "RSA" key type');
			}
			$json = vsprintf('{"e":"%s","kty":"%s","n":"%s"}', [
				$jwk['e'],
				$jwk['kty'],
				$jwk['n'],
			]);
		} else { // EC used with ES256 alg 
			if (!isset($jwk['crv'], $jwk['x'], $jwk['y'])) {
				throw new InvalidTokenException('JWK values doe not match "EC" key type');
			}
			//crv, kty, x, y
			$json = vsprintf('{"crv":"%s","kty":"%s","x":"%s","y":"%s"}', [
				$jwk['crv'],
				$jwk['kty'],
				$jwk['x'],
				$jwk['y']
			]);			
		}
		$hash    = hash('sha256', $json);
		$encoded = Base64Url::encode($hash);
		return $encoded;
	}

	/**
	 * https://datatracker.ietf.org/doc/html/draft-ietf-oauth-dpop#section-4.2
	 * When the DPoP proof is used in conjunction with the presentation of
	 * an access token in protected resource access, see Section 7, the DPoP
	 * proof MUST also contain the following claim:
     *   ath: hash of the access token.  The value MUST be the result of a
     *   base64url encoding (as defined in Section 2 of [RFC7515]) the
     *   SHA-256 [SHS] hash of the ASCII encoding of the associated access
     *   token's value.
     * See also: https://datatracker.ietf.org/doc/html/draft-ietf-oauth-dpop#section-7
     * 
	 * Validates the above part of the oauth dpop specification
	 *
	 * @param  string $jwt  JWT access token, raw
	 * @param  string $dpop DPoP token, raw
	 * @param  ServerRequestInterface $request Server Request
	 * @return bool true, if the dpop token "ath" claim matches the access token
	 */
	public function validateJwtDpop($jwt, $dpop, $request) {
		$this->validateDpop($dpop, $request);
		$jwtConfig = Configuration::forUnsecuredSigner();
		$jwtConfig->parser()->parse($dpop);

		/**
		 * @FIXME: ATH claim is not yet supported/required by the Solid OIDC specification.
		 *         Once the Solid spec catches up to the DPOP spec, not having an ATH is incorrect.
		 *         At that point, instead of returning "true", throw an exception:
		 *
		 * @see https://github.com/pdsinterop/php-solid-auth/issues/34
		 */
		// throw new InvalidTokenException('DPoP "ath" claim is missing');
		return true;
	}

	/**
	 * https://solidproject.org/TR/oidc#tokens-id
	 * validates that the provided OIDC ID Token matches the DPoP header
	 * @param  string $token The OIDS ID Token (raw)
	 * @param  string $dpop  The DPoP Token (raw)
	 * @param  ServerRequestInterface $request Server Request
	 * @return bool          True if the id token jkt matches the dpop token jwk
	 * @throws InvalidTokenException when the tokens do not match
	 */
	public function validateIdTokenDpop($token, $dpop, $request) {
		$this->validateDpop($dpop, $request);
		$jwtConfig = Configuration::forUnsecuredSigner();
		$jwt = $jwtConfig->parser()->parse($token);	
		$cnf = $jwt->claims()->get("cnf");

		if ($cnf === null) {
			throw new InvalidTokenException('JWT Confirmation claim (cnf) is missing');
		}

		if (!isset($cnf['jkt'])) {
			throw new InvalidTokenException('JWT Confirmation claim (cnf) is missing Thumbprint (jkt)');
		}

		$jkt = $cnf['jkt'];

		$dpopJwt = $jwtConfig->parser()->parse($dpop);
		$jwk = $dpopJwt->headers()->get('jwk');

		$jwkThumbprint = $this->makeJwkThumbprint($jwk);
		if ($jwkThumbprint !== $jkt) {
			throw new InvalidTokenException('ID Token JWK Thumbprint (jkt) does not match the JWK from DPoP header');
		}

		return true;
	}

	/**
	 * Validates that the DPOP token matches all requirements from 
	 * https://datatracker.ietf.org/doc/html/draft-ietf-oauth-dpop#section-4.2
	 *
	 * @param string $dpop The DPOP token
	 * @param ServerRequestInterface $request Server Request
	 *
	 * @return bool True if the DPOP token is valid
	 *
	 * @throws RequiredConstraintsViolated
	 * @throws InvalidTokenException
	 */
	public function validateDpop($dpop, $request) {
		/*
			4.2.  Checking DPoP Proofs
			   To check if a string that was received as part of an HTTP Request is
			   a valid DPoP proof, the receiving server MUST ensure that
			   1.  the string value is a well-formed JWT,
			   2.  all required claims are contained in the JWT,
			   3.  the "typ" field in the header has the value "dpop+jwt",
			   4.  the algorithm in the header of the JWT indicates an asymmetric
				   digital signature algorithm, is not "none", is supported by the
				   application, and is deemed secure,
			   5.  that the JWT is signed using the public key contained in the
				   "jwk" header of the JWT,
			   6.  the "htm" claim matches the HTTP method value of the HTTP request
				   in which the JWT was received (case-insensitive),
			   7.  the "htu" claims matches the HTTP URI value for the HTTP request
				   in which the JWT was received, ignoring any query and fragment
				   parts,
			   8.  the token was issued within an acceptable timeframe (see
				   Section 9.1), and
			   9.  that, within a reasonable consideration of accuracy and resource
				   utilization, a JWT with the same "jti" value has not been
				   received previously (see Section 9.1).
			  10.  that, if used with an access token, it also contains the 'ath' 
			       claim, with a hash of the access token
		*/
		// 1.  the string value is a well-formed JWT,
		$jwtConfig = Configuration::forUnsecuredSigner();
		try {
			$dpop = $jwtConfig->parser()->parse($dpop);
		} catch(\Exception $e) {
			throw new InvalidTokenException('Invalid DPoP token', 400, $e);
		}

	    // 2.  all required claims are contained in the JWT,
		$htm = $dpop->claims()->get("htm"); // http method
		if (!$htm) {
			throw new InvalidTokenException("missing htm");
		}
		$htu = $dpop->claims()->get("htu"); // http uri
		if (!$htu) {
			throw new InvalidTokenException("missing htu");
		}
		$typ = $dpop->headers()->get("typ");
		if (!$typ) {
			throw new InvalidTokenException("missing typ");
		}
		$alg = $dpop->headers()->get("alg");
		if (!$alg) {
			throw new InvalidTokenException("missing alg");
		}

		// 3.  the "typ" field in the header has the value "dpop+jwt",
		if ($typ != "dpop+jwt") {
			throw new InvalidTokenException("typ is not dpop+jwt");
		}

		// 4.  the algorithm in the header of the JWT indicates an asymmetric 
		//	   digital signature algorithm, is not "none", is supported by the
		//	   application, and is deemed secure,   
		if ($alg == "none") {
			throw new InvalidTokenException("alg is none");
		}

		// 5.  that the JWT is signed using the public key contained in the
		//     "jwk" header of the JWT,
		$jwk = $dpop->headers()->get("jwk");
		$webTokenJwk = JWK::createFromJson(json_encode($jwk));
		switch ($alg) {
			case "RS256":
				$pem = RSAKey::createFromJWK($webTokenJwk)->toPEM();
				$signer = new \Lcobucci\JWT\Signer\Rsa\Sha256();
			break;
			case "ES256":
				$pem = ECKey::convertToPEM($webTokenJwk);
                $signer = Sha256::create();
			break;
			default:
				throw new InvalidTokenException("unsupported algorithm");
			break;
		}
		$key = InMemory::plainText($pem);
		$validationConstraints = [];
		$validationConstraints[] = new SignedWith($signer, $key);

		// 6.  the "htm" claim matches the HTTP method value of the HTTP request
		//	   in which the JWT was received (case-insensitive),
		if (strtolower($htm) != strtolower($request->getMethod())) {
			throw new InvalidTokenException("htm http method is invalid");
		}

		// 7.  the "htu" claims matches the HTTP URI value for the HTTP request
		//     in which the JWT was received, ignoring any query and fragment
		// 	   parts,
		$requestedPath = (string)$request->getUri();
		$requestedPath = preg_replace("/[?#].*$/", "", $requestedPath);

		//error_log("REQUESTED HTU $htu");
		//error_log("REQUESTED PATH $requestedPath");
		if ($htu != $requestedPath) { 
			throw new InvalidTokenException("htu does not match requested path");
		}

		// 8.  the token was issued within an acceptable timeframe (see Section 9.1), and

		$leeway = new DateInterval("PT60S"); // allow 60 seconds clock skew
		$clock = SystemClock::fromUTC();
		$validationConstraints[] = new LooseValidAt($clock, $leeway); // It will use the current time to validate (iat, nbf and exp)
		if (!$jwtConfig->validator()->validate($dpop, ...$validationConstraints)) {
			$jwtConfig->validator()->assert($dpop, ...$validationConstraints); // throws an explanatory exception
		}

		// 9.  that, within a reasonable consideration of accuracy and resource utilization, a JWT with the same "jti" value has not been received previously (see Section 9.1).
		$jti = $dpop->claims()->get("jti");
		if ($jti === null) {
			throw new InvalidTokenException("jti is missing");
		}
		$isJtiValid = $this->jtiValidator->validate($jti, (string) $request->getUri());
		if (! $isJtiValid) {
			throw new InvalidTokenException("jti is invalid");
		}

		return true;
	}

	private function getSubjectFromJwt($jwt) {
		$jwtConfig = Configuration::forUnsecuredSigner();
		try {
			$jwt = $jwtConfig->parser()->parse($jwt);
		} catch(Exception $e) {
			throw new InvalidTokenException("Invalid JWT token", 409, $e);
		}

		$sub = $jwt->claims()->get("sub");
		if ($sub === null) {
			throw new InvalidTokenException('Missing "SUB"');
		}
		return $sub;
	}

	private function validateRequestHeaders($serverParams) {
		if (str_contains($serverParams['HTTP_AUTHORIZATION'], ' ') === false) {
			throw new AuthorizationHeaderException("Authorization Header does not contain parameters");
		}

		if (str_starts_with(strtolower($serverParams['HTTP_AUTHORIZATION']), 'dpop') === false) {
			throw new AuthorizationHeaderException('Only "dpop" authorization scheme is supported');
		}

		if (isset($serverParams['HTTP_DPOP']) === false) {
			throw new AuthorizationHeaderException("Missing DPoP token");
		}
	}
}
