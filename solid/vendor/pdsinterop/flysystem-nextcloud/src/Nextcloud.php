<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use OCP\Files\Folder;

/**
 * Filesystem adapter to access files in Nextcloud
 */
class Nextcloud implements AdapterInterface
{
    /** @var Folder */
    private $folder;

    final public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    final public function copy($path, $newpath)
    {
        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        $node->copy($newpath);

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     *
     * @throws \OCP\Files\NotPermittedException
     */
    final public function createDir($dirname, Config $config)
    {
        $this->folder->newFolder($dirname);

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     */
    final public function delete($path)
    {
        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        $node->delete();

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     */
    final public function deleteDir($dirname)
    {
        $result = false;

        try {
            $node = $this->folder->get($dirname);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if ($this->isDirectory($node)) {
            $node->delete();
            $result = true;
        }

        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function getMetadata($path)
    {
        try {
            $node = $this->folder->get($path);
            return $this->normalizeNodeInfo($node);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getMimeType($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    final public function has($path)
    {
        return $this->folder->nodeExists($path);
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    final public function listContents($directory = '', $recursive = false)
    {
        $result = [];

        try {
            $node = $this->folder->get("/" . $directory);
        } catch (\OCP\Files\NotFoundException $exception) {
            return [];
        }

        if (method_exists($node, 'getDirectoryListing')) {
            $nodes = $node->getDirectoryListing();

            $result = array_map(function (\OCP\Files\Node $node) {
                return $this->normalizeNodeInfo($node);
            }, $nodes);
        }

        return $result;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function read($path)
    {
        $result = false;

        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if (method_exists($node, 'getContent')) {
            $result = $this->normalizeNodeInfo($node, [
                'contents' => $node->getContent(),
            ]);
        }

        return $result;
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function readStream($path)
    {
        $result = false;

        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if (method_exists($node, 'fopen')) {
            $result = $this->normalizeNodeInfo($node, [
                'stream' => $node->fopen('rb'),
            ]);
        }

        return $result;
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     * @throws \OCP\Lock\LockedException
     */
    final public function rename($path, $newpath)
    {
        try {
            $this->folder->get($path)->move($newpath);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    final public function setVisibility($path, $visibility)
    {
        return false;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     *
     * @throws \OCP\Files\NotPermittedException
     */
    final public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function write($path, $contents, Config $config)
    {
        $result = true;

        try {
            if ($this->folder->nodeExists($path)) {
                $node = $this->folder->get($path);
                if (method_exists($node, 'putContent')) {
                    $node->putContent($contents);

                    $result = $this->normalizeNodeInfo($node, [
                        'contents' => $node->getContent(),
                    ]);
                }
            } else {
                $filename = basename($path);
                $dirname = dirname($path);
                if (!$this->folder->nodeExists($dirname)) {
                    $this->folder->newFolder($dirname);
                }
                $node = $this->folder->get($dirname);
                $node->newFile($filename, $contents);
            }
        } catch(\Exception $e) {
            return false;
        }

        return $result;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     *
     * @throws \OCP\Files\NotPermittedException
     */
    final public function writeStream($path, $resource, Config $config)
    {
        $result = false;

        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if (method_exists($node, 'fopen')) {
            $dirname = dirname($path);
            $folder = $this->folder->nodeExists($dirname);

            // @CHECKME: Do we need to create a directory or will the Node do that for us?
            if ($folder === false) {
                $this->createDir($dirname, $config);
            }

            $stream = $node->fopen('w+b');

            if (stream_copy_to_stream($resource, $stream) === false) {
                fclose($stream);
                return false;
            }

            $result = [
                'type' => 'file',
                'path' => $path,
            ];
        }

        return $result;
    }

    /**
     * @param \OCP\Files\Node $node
     *
     * @return bool
     */
    private function isDirectory(\OCP\Files\Node $node)
    {
        return $node->getType() === \OCP\Files\FileInfo::TYPE_FOLDER;
    }

    /**
     * @param \OCP\Files\Node $node
     * @param array $metaData
     *
     * @return array
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    private function normalizeNodeInfo(\OCP\Files\Node $node, array $metaData = []) : array
    {
        return array_merge([
            'mimetype' => $this->isDirectory($node) ? "directory" : $node->getMimetype(),
            'path' => substr($node->getPath(), strlen($this->folder->getPath())+1),
            'size' => $node->getSize(),
            'basename' => basename($node->getPath()),
            'timestamp' => $node->getMTime(),
            'type' => $node->getType(),
            // @FIXME: Use $node->getPermissions() to set private or public
            //         as soon as we figure out what Nextcloud permissions mean in this context
            'visibility' => 'public',
            /*/
            'CreationTime' => $node->getCreationTime(),
            'Etag' => $node->getEtag(),
            'Owner' => $node->getOwner(),
            /*/
        ], $metaData);
    }
}
