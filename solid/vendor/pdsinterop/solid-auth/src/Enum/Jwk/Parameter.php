<?php

namespace Pdsinterop\Solid\Auth\Enum\Jwk;

class Parameter
{
    public const ALGORITHM = 'alg';

    public const KEY_ID = 'kid';

    public const KEY_OPERATIONS = 'key_ops';

    public const KEY_TYPE = 'kty';

    public const PUBLIC_KEY_USE = 'use';

    public const X_509_CERTIFICATE_CHAIN = 'x5c';

    public const X_509_CERTIFICATE_SHA_1_THUMBPRINT = 'x5t';

    public const X_509_CERTIFICATE_SHA_256_THUMBPRINT = 'x5t#S256';

    public const X_509_URL = 'x5u';
}
