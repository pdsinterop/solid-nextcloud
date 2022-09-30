<?php

namespace Pdsinterop\Rdf\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;

/**
 * Filesystem adapter to convert RDF files to and from a default format
 */
interface RdfAdapterInterface extends AdapterInterface
{
    public function getFormat(): string;

    public function setFormat(string $format): void;
}
