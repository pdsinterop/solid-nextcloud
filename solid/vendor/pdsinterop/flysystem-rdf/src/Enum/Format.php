<?php declare(strict_types=1);

namespace Pdsinterop\Rdf\Enum;

class Format
{
    public const JSON_LD = 'jsonld';    // Requires `ml/json-ld`
    public const NOTATION_3 = 'n3';
    public const N_TRIPLES = 'ntriples';
    public const RDF_XML = 'rdfxml';
    public const TURTLE = 'turtle';
    public const UNKNOWN = 'guess';

    final public static function has($value): bool
    {
        return in_array($value, self::keys(), true);
    }

    /**
     * Return array of available keys and their values.
     *
     * @return string[]
     */
    final public static function keys(): array
    {
        $keys = (new \ReflectionClass(__CLASS__))->getConstants();

        return array_filter($keys, static function ($value) {
            return $value !== self::UNKNOWN;
        });
    }
}
