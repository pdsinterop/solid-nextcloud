<?php

namespace Pdsinterop\Solid\Auth\Utils;

/**
 * URL-safe Base64 encode and decode
 *
 * ...as PHP does not natively offer this functionality
 */
class Base64Url
{
    private const URL_UNSAFE = '+/';
    private const URL_SAFE = '-_';

    public static function encode($subject) : string
    {
        return strtr(rtrim(base64_encode($subject), '='), self::URL_UNSAFE, self::URL_SAFE);
    }

    public static function decode($subject) : string
    {
        return base64_decode(strtr($subject, self::URL_SAFE, self::URL_UNSAFE));
    }
}
