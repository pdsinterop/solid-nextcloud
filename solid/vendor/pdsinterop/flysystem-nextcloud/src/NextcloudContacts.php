<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

use OC;
use OCA\DAV\CalDav\Proxy\ProxyMapper;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\Sabre\Principal;
use Sabre\DAV\Exception\BadRequest;


/**
 * Filesystem adapter to access contacts information from Nextcloud
 */
class NextcloudContacts implements AdapterInterface
{
    use StreamedTrait;

    /** @var CardDavBackend */
    private $cardDavBackend;
    /** @var string */
    private $defaultAcl;
    /** @var string */
    private $principalUri;
    /** @var string */
    private $userId;

    final public function __construct($userId, $defaultAcl)
    {
        $this->userId = $userId;
        $this->principalUri = 'principals/users/' . $this->userId;
        $this->defaultAcl = $defaultAcl;

        $principalBackend = new Principal(
            OC::$server->getUserManager(),
            OC::$server->getGroupManager(),
            OC::$server->getShareManager(),
            OC::$server->getUserSession(),
            OC::$server->getAppManager(),
            OC::$server->query(ProxyMapper::class),
            OC::$server->getConfig(),
            'principals/'
        );
        $db = OC::$server->getDatabaseConnection();
        $userManager = OC::$server->getUserManager();
        $dispatcher = OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class);
        $legacyDispatcher = OC::$server->getEventDispatcher();

        $this->cardDavBackend = new CardDavBackend(
            $db,
            $principalBackend,
            $userManager,
            OC::$server->getGroupManager(),
            $dispatcher,
            $legacyDispatcher,
            true
        );
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
        // FIXME: Implementation
        return false;
    }

    /**
     * Create an address book.
     *
     * @param string $addressBookName address book name
     * @param Config $config
     *
     * @return array|false
     *
     * @throws BadRequest
     */
    final public function createDir($addressBookName, Config $config)
    {
        $addressBookId = $this->cardDavBackend->createAddressBook($this->principalUri, $addressBookName, array());
        if ($addressBookId !== null) {
            return ['path' => $addressBookName, 'type' => 'dir'];
        }
        return false;
    }

    /**
     * Delete a card.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function delete($path)
    {
        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $this->cardDavBackend->deleteCard($addressBookId, $filename);
        return true;
    }

    /**
     * Delete an addressBook.
     *
     * @param string $addressBook
     *
     * @return bool
     */
    final public function deleteDir($addressBook)
    {
        $addressBookId = $this->getAddressBookId($addressBook);
        if (!$addressBookId) {
            return false;
        }

        $this->cardDavBackend->deleteAddressBook($addressBookId);
        return true;
    }

    private function getAddressBookId($path) {
        $path = explode('/', $path);
        if (count($path) === 1) {
            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $path[0]);
            if ($addressBook) {
                return $addressBook['id'];
            }
        }

        return null;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getMetadata($path)
    {
        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId !== null) {
            $addressBook = $this->cardDavBackend->getAddressBookById($addressBookId);
            return $this->normalizeAddressBook($addressBook);
        } else {
            list($addressBook, $filename) = $this->splitPath($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);
            return $this->normalizeCard($card, $addressBook);
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
     * @return bool
     */
    final public function has($path)
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return true;
        }

        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId !== null) {
            return true;
        } else {
            list($addressBook, $filename) = $this->splitPath($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);

            return is_array($card);
        }
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    final public function listContents($directory = '', $recursive = false)
    {
        if ($directory === '') {
            $addressBooks = $this->cardDavBackend->getAddressBooksForUser($this->userId);

            return array_map(function ($addressBook) {
                return $this->normalizeAddressBook($addressBook);
            }, $addressBooks);
        } else {
            $directory = basename($directory);

            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $directory);
            $cards = $this->cardDavBackend->getCards($addressBook['id']);

            $contents = [];
            foreach ($cards as $card) {
                $contents[] = $this->cardDavBackend->getCard($addressBook['id'], $card['uri']);
            }

            return array_map(function($card) use ($directory) {
                return $this->normalizeCard($card, $directory);
            }, $contents);
        }
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function read($path)
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return $this->normalizeAcl($this->defaultAcl);
        }

        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $card = $this->cardDavBackend->getCard($addressBookId, $filename);

        return $this->normalizeCard($card, $addressBook);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    final public function rename($path, $newpath)
    {
        return false;
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
     */
    final public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
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
     * @throws BadRequest
     */
    final public function write($path, $contents, Config $config)
    {
        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        if ($this->has($path)) {
            $this->cardDavBackend->updateCard($addressBookId, $filename, $contents);
        } else {
            $this->cardDavBackend->createCard($addressBookId, $filename, $contents);
        }
        return true;
    }

    private function normalizeAcl($acl) {
        return [
            'basename' => '.acl',
            'contents' => $acl,
            'mimetype' => 'text/turtle',
            'path' => '.acl',
            'size' => strlen($acl),
            'timestamp' => 0,
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeCard($card, $basePath) {
        if ( ! is_array($card)) {
            return false;
        }

        return [
            'basename' => $card['uri'],
            'contents' => $card['carddata'],
            'mimetype' => 'text/vcard',
            'path' => $basePath . '/' . $card['uri'],
            'size' => $card['size'],
            'timestamp' => $card['lastmodified'],
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeAddressBook($addressBook)
    {
        return [
            'basename' => basename($addressBook['uri']),
            'mimetype' => 'directory',
            'path' => $addressBook['uri'],
            'size' => 0,
            'timestamp' => 0,
            'type' => 'dir',
            // @FIXME: Use $node->getPermissions() to set private or public
            //         as soon as we figure out what Nextcloud permissions mean in this context
            'visibility' => 'public',
            /*/
            'CreationTime' => $node->getCreationTime(),
            'Etag' => $node->getEtag(),
            'Owner' => $node->getOwner(),
            /*/
        ];
    }

    /**
     * @param string $path
     *
     * @return string[]
     */
    private function splitPath(string $path)
    {
        return [
            dirname($path),
            basename($path)
        ];
    }
}
