<?php

namespace Pdsinterop\Solid\Auth\Utils;

use DateInterval;
use Pdsinterop\Solid\Auth\ReplayDetectorInterface;

/**
 * Validates whether a provided JTI (JWT ID) is valid.
 *
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-oauth-dpop
 */
class JtiValidator
{
    final public function __construct(private ReplayDetectorInterface $replayDetector) {}

    public function validate($jti, $targetUri): bool
    {
        $isValid = false;

        $strlen = mb_strlen($jti);
        /* At least 96 bits of pseudorandom data are required,
         * which is 12 characters (or 24 hexadecimal characters)
         * The upper limit is chosen based on maximum field length in common database storage types (varchar)
         */
        if ($strlen > 12 && $strlen < 256) {
            // @CHECKME: Should we fail silently (return false) or loudly (throw InvalidTokeException)?
            $isValid = $this->replayDetector->detect($jti, $targetUri) === false;
        }

        return $isValid;
    }
}
