<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/** a clone of the N3Util class from the N3js code by Ruben Verborgh **/
class Util
{
    const XSD = 'http://www.w3.org/2001/XMLSchema#';
    const XSDSTRING = self::XSD.'string';
    const XSDINTEGER = self::XSD.'integer';
    const XSDDECIMAL = self::XSD.'decimal';
    const XSDFLOAT = self::XSD.'float';
    const XSDDOUBLE = self::XSD.'double';
    const XSDBOOLEAN = self::XSD.'boolean';
    const RDFLANGSTRING = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';

    /**
     * Tests whether the given entity (triple object) represents an IRI.
     */
    public static function isIRI(?string $term): bool
    {
        if (!$term) {
            return false;
        }
        $firstChar = substr($term, 0, 1);

        return '"' !== $firstChar && '_' !== $firstChar;
    }

    public static function isLiteral(?string $term): bool
    {
        return $term && '"' === substr($term, 0, 1);
    }

    public static function isBlank(?string $term): bool
    {
        return $term && '_:' === substr($term, 0, 2);
    }

    public static function isDefaultGraph(?string $term): bool
    {
        return empty($term);
    }

    /**
     * Tests whether the given $triple is in the default graph
     *
     * @param array<string, string|null> $triple
     */
    public static function inDefaultGraph(array $triple): bool
    {
        return !$triple['graph'];
    }

    /**
     * Gets the string value of a literal in the N3 library
     *
     * @return string|int|float|null
     */
    public static function getLiteralValue(string $literal)
    {
        preg_match('/^"(.*)"/s', $literal, $match); //TODO: somehow the copied regex did not work. To be checked. Contained [^]
        if (empty($match)) {
            throw new \Exception($literal.' is not a literal');
        }

        return $match[1];
    }

    // Gets the type of a literal in the N3 library
    public static function getLiteralType(string $literal): string
    {
        preg_match('/^".*"(?:\^\^([^"]+)|(@)[^@"]+)?$/s', $literal, $match); //TODO: somehow the copied regex did not work. To be checked. Contained [^] instead of the .
        if (empty($match)) {
            throw new \Exception($literal.' is not a literal');
        }
        if (!empty($match[1])) {
            return $match[1];
        } else {
            return !empty($match[2]) ? self::RDFLANGSTRING : self::XSDSTRING;
        }
    }

    // Gets the language of a literal in the N3 library
    public static function getLiteralLanguage(string $literal): string
    {
        preg_match('/^".*"(?:@([^@"]+)|\^\^[^"]+)?$/s', $literal, $match);
        if (empty($match)) {
            throw new \Exception($literal.' is not a literal');
        }

        return isset($match[1]) ? strtolower($match[1]) : '';
    }

    /**
     * Tests whether the given entity ($triple object) represents a prefixed name
     */
    public static function isPrefixedName(?string $term): bool
    {
        return !empty($term) && preg_match("/^[^:\/\"']*:[^:\/\"']+$/", $term);
    }

    /**
     * Expands the prefixed name to a full IRI (also when it occurs as a literal's type)
     *
     * @param array<string, string>|null $prefixes
     */
    public static function expandPrefixedName(string $prefixedName, ?array $prefixes = null): string
    {
        preg_match("/(?:^|\"\^\^)([^:\/#\"'\^_]*):[^\/]*$/", $prefixedName, $match, PREG_OFFSET_CAPTURE);
        $prefix = '';
        $base = '';
        $index = '';

        if (!empty($match)) {
            $prefix = $match[1][0];
            $base = '';
            if (isset($prefixes[$prefix])) {
                $base = $prefixes[$prefix];
            } else {
                $base = null;
            }
            $index = $match[1][1];
        }
        if (!$base) {
            return $prefixedName;
        }

        // The match index is non-zero when expanding a literal's type
        if (0 === $index) {
            // base + prefixedName.substr(prefix.length + 1)
            return $base.substr($prefixedName, \strlen($prefix) + 1);
        } else {
            // prefixedName.substr(0, index + 3) + base + prefixedName.substr(index + prefix.length + 4);
            return substr($prefixedName, 0, $index).$base.substr($prefixedName, $index + \strlen($prefix) + 1);
        }
    }

    /**
     * Creates an IRI
     *
     * @return float|int|string|null
     */
    public static function createIRI(?string $iri)
    {
        return !empty($iri) && '"' === substr($iri, 0, 1) ? self::getLiteralValue($iri) : $iri;
    }

    /**
     * Creates a literal
     *
     * @param string|null $modifier
     */
    public static function createLiteral($value, $modifier = null): string
    {
        if (!$modifier) {
            switch (\gettype($value)) {
                case 'boolean':
                    $value = $value ? 'true' : 'false';
                    $modifier = self::XSDBOOLEAN;
                    break;
                case 'integer':
                    $modifier = self::XSDINTEGER;
                    break;
                case 'double':
                    $modifier = self::XSDDOUBLE;
                    break;
                case 'float':
                    $modifier = self::XSDFLOAT;
                    break;
                default:
                    return '"'.$value.'"';
            }
        }

        $result = '"'.$value;

        if (preg_match('/^[a-z]+(-[a-z0-9]+)*$/i', $modifier)) {
            $result .= '"@'.strtolower($modifier);
        } else {
            $result .= '"^^'.$modifier;
        }

        return $result;
    }
}
