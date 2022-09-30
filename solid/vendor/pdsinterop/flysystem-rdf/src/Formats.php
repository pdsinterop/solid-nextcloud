<?php

namespace Pdsinterop\Rdf;

use Pdsinterop\Rdf\Enum\Format;

class Formats implements FormatsInterface
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private $data = [
        Format::JSON_LD => [
            'uri' => 'http://www.w3.org/TR/json-ld/',
            'mimeTypes' => [
                'application/ld+json' => 1.0,
            ],
            'extensions' => ['jsonld', 'json'],
        ],
        Format::N_TRIPLES => [
            'uri' => 'http://www.w3.org/TR/n-triples/',
            'mimeTypes' => [
                'application/n-triples' => 1.0,
                'text/plain' => 0.9,
                'text/ntriples' => 0.9,
                'application/ntriples' => 0.9,
                'application/x-ntriples' => 0.9,
            ],
            'extensions' => ['nt'],
        ],
        Format::TURTLE => [
            'uri' => 'http://www.dajobe.org/2004/01/turtle',
            'mimeTypes' => [
                'text/turtle' => 0.8,
                'application/turtle' => 0.7,
                'application/x-turtle' => 0.7,
            ],
            'extensions' => ['ttl'],
        ],
        Format::RDF_XML => [
            'uri' => 'http://www.w3.org/TR/rdf-syntax-grammar',
            'mimeTypes' => [
                'application/rdf+xml' => 0.8,
            ],
            'extensions' => ['rdf', 'xrdf', 'html'],
        ],
        Format::NOTATION_3 => [
            'uri' => 'http://www.w3.org/2000/10/swap/grammar/n3#',
            'mimeTypes' => [
                'text/n3' => 0.5,
                'text/rdf+n3' => 0.5,
            ],
            'extensions' => ['n3'],
        ],
    ];

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getAllExtensions(): array
    {
        return $this->getAll('extensions');
    }

    final public function getAllMimeTypes(): array
    {
        $all = $this->getAll('mimeTypes');

        return array_map(static function ($mimeTypes) {
            return array_keys($mimeTypes);
        }, $all);
    }

    private function getData(): array
    {
        return $this->data;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function getExtensionsForFormat(string $format): array
    {
        return $this->getAllExtensions()[$format] ?? [];
    }

    final public function getFormatForExtension(string $extension): string
    {
        $extension = ltrim($extension, '.');

        $all = $this->getAllExtensions();

        $formats = array_filter($all, static function ($extensions) use ($extension) {
            return in_array($extension, $extensions, true);
        });

        $formatNames = array_keys($formats);

        return reset($formatNames);
    }

    final public function getFormatForMime(string $mime): string
    {
        $all = $this->getAllMimeTypes();

        $formats = array_filter($all, static function ($mimes) use ($mime) {
            return in_array($mime, $mimes, true);
        });

        $formatNames = array_keys($formats);

        return reset($formatNames);
    }

    final public function getMimeForExtension(string $extension): string
    {
        $format = $this->getFormatForExtension($extension);

        return $this->getMimeForFormat($format);
    }

    final public function getMimeForFormat(string $format): string
    {
        $mimes = $this->getMimesForFormat($format);

        return reset($mimes);
    }

    final public function getMimesForFormat(string $format): array
    {
        return $this->getAllMimeTypes()[$format] ?? [];
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function getAll(string $subject): array
    {
        $data = $this->getData();

        return array_column(array_map(static function ($key, $values) use ($subject) {
            return [$key, $values[$subject]];
        }, array_keys($data), $data), 1, 0);
    }
}
