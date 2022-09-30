<?php

namespace Pdsinterop\Rdf;

interface FormatsInterface
{
    public function getAllExtensions(): array;

    public function getAllMimeTypes(): array;

    public function getExtensionsForFormat(string $format): array;

    public function getFormatForExtension(string $extension): string;

    public function getFormatForMime(string $mime): string;

    public function getMimeForExtension(string $extension): string;

    public function getMimeForFormat(string $format): string;

    public function getMimesForFormat(string $format): array;
}
