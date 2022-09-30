<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

use OC;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Connector\Sabre\Principal;
use Sabre\DAV\Exception;
use Sabre\DAV\Exception\BadRequest;


/**
 * Filesystem adapter to access calendar information from Nextcloud
 */
class NextcloudCalendar implements AdapterInterface
{
    use StreamedTrait;

    /** @var CalDavBackend */
    private $calDavBackend;
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
        $random = OC::$server->getSecureRandom();
        $logger = OC::$server->getLogger();
        $dispatcher = OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class);
        $legacyDispatcher = OC::$server->getEventDispatcher();
        $this->calDavBackend = new CalDavBackend(
            $db,
            $principalBackend,
            $userManager,
            OC::$server->getGroupManager(),
            $random,
            $logger,
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
     * Create a calendar.
     *
     * @param string $calendarName calendar name
     * @param Config $config
     *
     * @return array|false
     *
     * @throws Exception
     */
    final public function createDir($calendarName, Config $config)
    {
        $calendarId = $this->calDavBackend->createCalendar($this->principalUri, $calendarName, []);
        if ($calendarId !== null) {
            return ['path' => $calendarName, 'type' => 'dir'];
        }
        return false;
    }

    /**
     * Delete a calendar item.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function delete($path)
    {
        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        $this->calDavBackend->deleteCalendarObject($calendarId, $filename);
        return true;
    }

    /**
     * Delete a calendar.
     *
     * @param string $calendar
     *
     * @return bool
     */
    final public function deleteDir($calendar)
    {
        $calendarId = $this->getCalendarId($calendar);
        if (!$calendarId) {
            return false;
        }

        $this->calDavBackend->deleteCalendar($calendarId);
        return true;
    }

    private function getCalendarId($path) {
        $path = explode('/', $path);
        if (count($path) === 1) {
            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $path[0]);
            if ($calendar) {
                return $calendar['id'];
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
        $calendarId = $this->getCalendarId($path);
        if ($calendarId !== null) {
            $calendar = $this->calDavBackend->getCalendarById($calendarId);
            return $this->normalizeCalendar($calendar);
        } else {
            list($calendar, $filename) = $this->splitPath($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
            return $this->normalizeCalendarItem($calendarItem, $calendar);
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

        $calendarId = $this->getCalendarId($path);
        if ($calendarId !== null) {
            return true;
        } else {
            list($calendar, $filename) = $this->splitPath($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);

            return is_array($calendarItem);
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
            $calendars = $this->calDavBackend->getCalendarsForUser($this->userId);

            return array_map(function ($calendar) {
                return $this->normalizeCalendar($calendar);
            }, $calendars);
        } else {
            $directory = basename($directory);

            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $directory);
            $calendarObjects = $this->calDavBackend->getCalendarObjects($calendar['id']);

            $contents = [];
            foreach ($calendarObjects as $calendarObject) {
                $contents[] = $this->calDavBackend->getCalendarObject($calendarObject['calendarid'], $calendarObject['uri']);
            }

            return array_map(function($calendarItem) use ($directory) {
                return $this->normalizeCalendarItem($calendarItem, $directory);
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

        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);

        return $this->normalizeCalendarItem($calendarItem, $calendar);
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
        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        if ($this->has($path)) {
            $this->calDavBackend->updateCalendarObject($calendarId, $filename, $contents);
        } else {
            $this->calDavBackend->createCalendarObject($calendarId, $filename, $contents);
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

    private function normalizeCalendarItem($calendarItem, $basePath) {
        if ( ! is_array($calendarItem)) {
            return false;
        }

        return [
            'basename' => $calendarItem['uri'],
            'contents' => $calendarItem['calendardata'],
            'mimetype' => 'text/calendar',
            'path' => $basePath . '/' . $calendarItem['uri'],
            'size' => $calendarItem['size'],
            'timestamp' => $calendarItem['lastmodified'],
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeCalendar($calendar)
    {
        return [
            'basename' => basename($calendar['uri']),
            'mimetype' => 'directory',
            'path' => $calendar['uri'],
            'size' => 0,
            'timestamp' => 0,
            'type' => 'dir',
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
