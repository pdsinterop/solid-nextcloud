<?php declare(strict_types=1);

namespace Pdsinterop\Rdf\Flysystem\Plugin;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Plugin\AbstractPlugin;
use Pdsinterop\Rdf\Flysystem\Adapter\RdfAdapterInterface;
use Pdsinterop\Rdf\Flysystem\Exception;
use Pdsinterop\Rdf\FormatsInterface;

class AsMime extends AbstractPlugin
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public const ERROR_MISSING_ADAPTER = 'Plugin %s can not be used before an adapter has been added to the filesystem.';

    /** @var FormatsInterface */
    private $formats;

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return 'asMime';
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(FormatsInterface $formats)
    {
        $this->formats = $formats;
    }

    public function handle(string $mime): FilesystemInterface
    {
        $filesystem = $this->filesystem;

        if (! is_callable([$filesystem, 'getAdapter'])) {
            throw Exception::create(self::ERROR_MISSING_ADAPTER, [__CLASS__]);
        }

        if ($filesystem->getAdapter() instanceof RdfAdapterInterface) {
            $format = $this->formats->getFormatForMime($mime);
            $filesystem->getAdapter()->setFormat($format);
        }

        return $filesystem;
    }
}
