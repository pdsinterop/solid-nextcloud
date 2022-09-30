<?php

namespace Pdsinterop\Solid\Auth\Enum\Rsa;

/**
 * Parameters for RSA Public Keys
 *
 * These members MUST be present for RSA public keys.
 *
 * The RSA Key blinding operation [Kocher], which is a defense against some
 * timing attacks, requires all of the RSA key values "n", "e", and "d".
 *
 * However, some RSA private key representations do not include the public
 * exponent "e", but only include the modulus "n" and the private exponent
 * "d". This is true, for instance, of the Java RSAPrivateKeySpec API, which
 * does not include the public exponent "e" as a parameter. So as to enable
 * RSA key blinding, such representations should be avoided. For Java, the
 * RSAPrivateCrtKeySpec API can be used instead. Section 8.2.2(i) of the
 * "Handbook of Applied Cryptography" [HAC] discusses how to compute the
 * remaining RSA private key parameters, if needed, using only "n", "e",
 * and "d".}
*
 * @see https://tools.ietf.org/html/rfc7518#section-6.3
 */
class Parameter
{
    /**
     * The "e" (exponent) parameter contains the exponent value for the RSA
     * public key. It is represented as a Base64urlUInt-encoded value. For
     * instance, when representing the value 65537, the octet sequence to be
     * base64url-encoded MUST consist of the three octets [1, 0, 1]; the
     * resulting representation for this value is "AQAB".
     */
    public const PUBLIC_EXPONENT = 'e';

    /**
     * The "n" (modulus) parameter contains the modulus value for the RSA public
     * key.  It is represented as a Base64urlUInt-encoded value.
     */
    public const PUBLIC_MODULUS = 'n';

    /**
     * The "d" (private exponent) parameter contains the private exponent value
     * for the RSA private key.  It is represented as a Base64urlUInt-encoded
     * value.}
     */
    public const PRIVATE_EXPONENT = 'd';
}
