<?php

namespace Ayacoo\MongoDB\Cache\Backend;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Exception;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;

/**
 * A caching backend which stores cache entries by using MongoDB with MongoDB
 * PHP module. MongoDB is a source-available cross-platform document-oriented database program. Classified
 * as a NoSQL database program, MongoDB uses JSON-like documents with optional schemas.
 */
class MongoBackend extends AbstractBackend implements TaggableBackendInterface
{
    private const DEFAULT_LIFETIME = 3600;

    /**
     * MongoDB collection
     */
    private Collection $collection;

    /**
     * MongoDB database
     */
    private Database $database;

    /**
     * Port of the MongoDB server, defaults to 27017
     */
    protected int $port = 27017;

    /**
     * MongoDB database name
     */
    private string $databaseName = 'typo3';

    /**
     * MongoDB collection name
     */
    private string $collectionName = 'test';

    /**
     * Hostname / IP of the MongoDB server, defaults to mongo (ddev setting)
     */
    protected string $hostname = 'mongo';

    /**
     * User for MongoDB authentication
     */
    protected string $user = 'db';

    /**
     * Password for MongoDB authentication
     */
    protected string $password = 'db';

    /**
     * Indicates whether the server is connected
     */
    protected bool $connected = false;

    /**
     * Construct this backend
     *
     * @param string $context Unused, for backward compatibility only
     * @param array $options Configuration options
     * @throws Exception if php MongoDB module is not loaded
     */
    public function __construct($context, array $options = [])
    {
        if (!extension_loaded('MongoDB')) {
            throw new Exception('The PHP extension "MongoDB" must be installed and loaded in order to use the MongoDB backend.', 1631360188);
        }

        parent::__construct($context, $options);
    }

    /**
     * Initializes the MongoDB backend
     */
    public function initializeObject()
    {
        $mongoUri = $this->buildMongoDBUri();

        $databaseName = $this->databaseName;
        $collectionName = $this->collectionName;

        $client = new Client($mongoUri);
        try {
            $this->connected = true;
            $this->collection = $client->$databaseName->$collectionName;
            $this->database = $client->selectDatabase($databaseName);
        } catch (\Exception $e) {
            $this->connected = false;
            $this->logger->alert('Could not connect to MongoDB server.', ['exception' => $e]);
        }
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @param string $databaseName
     */
    public function setDatabaseName(string $databaseName): void
    {
        $this->databaseName = $databaseName;
    }

    /**
     * @param string $collectionName
     */
    public function setCollectionName(string $collectionName): void
    {
        $this->collectionName = $collectionName;
    }

    /**
     * @param string $hostname
     */
    public function setHostname(string $hostname): void
    {
        $this->hostname = $hostname;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Save data in the cache
     *
     * @param string $entryIdentifier Identifier for this specific cache entry
     * @param string $data Data to be stored
     * @param array $tags Tags to associate with this cache entry
     * @param int $lifetime Lifetime of this cache entry in seconds. If NULL is specified, default lifetime is used.
     * @throws \InvalidArgumentException if identifier is not valid
     * @throws InvalidDataException if data is not a string
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        if (!$this->canBeUsedInStringContext($entryIdentifier)) {
            throw new \InvalidArgumentException('The specified identifier is of type "' . gettype($entryIdentifier) . '" which can\'t be converted to string.', 1377006651);
        }
        if (!is_string($data)) {
            throw new InvalidDataException('The specified data is of type "' . gettype($data) . '" but a string is expected.', 1279469941);
        }
        $lifetime = $lifetime ?? self::DEFAULT_LIFETIME;
        if (!is_int($lifetime)) {
            throw new \InvalidArgumentException('The specified lifetime is of type "' . gettype($lifetime) . '" but an integer or NULL is expected.', 1279488008);
        }
        if ($lifetime < 0) {
            throw new \InvalidArgumentException('The specified lifetime "' . $lifetime . '" must be greater or equal than zero.', 1279487573);
        }
        if ($this->connected) {
            $dateAsTimestamp = time() + $lifetime;
            $this->collection->insertOne([
                'key' => $entryIdentifier,
                'value' => $data,
                'tags' => $tags,
                'lifetime' => new UTCDateTime($dateAsTimestamp * 1000)
            ]);
        }
    }

    /**
     * Loads data from the cache.
     *
     * @param string $entryIdentifier An identifier which describes the cache entry to load
     * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
     * @throws \InvalidArgumentException if identifier is not a string
     */
    public function get($entryIdentifier)
    {
        if (!$this->canBeUsedInStringContext($entryIdentifier)) {
            throw new \InvalidArgumentException('The specified identifier is of type "' . gettype($entryIdentifier) . '" which can\'t be converted to string.', 1377006652);
        }
        if ($this->connected) {
            $result = $this->collection->find(['key' => $entryIdentifier]);
            foreach ($result as $entry) {
                return $entry['value'] ?? '';
            }
        }

        return false;
    }

    /**
     * Checks if a cache entry with the specified identifier exists.
     *
     * @param string $entryIdentifier Identifier specifying the cache entry
     * @return bool TRUE if such an entry exists, FALSE if not
     * @throws \InvalidArgumentException if identifier is not a string
     */
    public function has($entryIdentifier)
    {
        if (!$this->canBeUsedInStringContext($entryIdentifier)) {
            throw new \InvalidArgumentException('The specified identifier is of type "' . gettype($entryIdentifier) . '" which can\'t be converted to string.', 1377006653);
        }
        if ($this->connected) {
            $result = $this->collection->find(['key' => $entryIdentifier]);
            foreach ($result as $entry) {
                return !empty($entry);
            }
        }

        return false;
    }

    /**
     * Removes all cache entries matching the specified identifier.
     *
     * @param string $entryIdentifier Specifies the cache entry to remove
     * @return bool TRUE if (at least) an entry could be removed or FALSE if no entry was found
     * @throws \InvalidArgumentException if identifier is not a string
     */
    public function remove($entryIdentifier)
    {
        if (!$this->canBeUsedInStringContext($entryIdentifier)) {
            throw new \InvalidArgumentException('The specified identifier is of type "' . gettype($entryIdentifier) . '" which can\'t be converted to string.', 1377006654);
        }
        if ($this->connected) {
            $result = $this->collection->deleteMany(
                ['key' => $entryIdentifier]
            );

            if ($result->getDeletedCount() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds and returns all cache entry identifiers which are tagged by the
     * specified tag.
     *
     * @param string $tag The tag to search for
     * @return array An array of entries with all matching entries. An empty array if no entries matched
     * @throws \InvalidArgumentException if tag is not a string
     */
    public function findIdentifiersByTag($tag)
    {
        if (!$this->canBeUsedInStringContext($tag)) {
            throw new \InvalidArgumentException('The specified tag is of type "' . gettype($tag) . '" which can\'t be converted to string.', 1377006655);
        }
        if ($this->connected) {
            return $this->collection->find(['tags' => $tag]);
        }

        return [];
    }

    /**
     * Removes all cache entries of this cache.
     * For performance reasons, the collection is deleted and then rebuilt.
     */
    public function flush()
    {
        if ($this->connected) {
            $this->collection->drop();
            $this->database->command([
                'create' => $this->collectionName
            ]);
        }
    }

    /**
     * Removes all cache entries of this cache which are tagged with the specified tag.
     *
     * @param string $tag Tag the entries must have
     * @throws \InvalidArgumentException if identifier is not a string
     */
    public function flushByTag($tag)
    {
        if (!$this->canBeUsedInStringContext($tag)) {
            throw new \InvalidArgumentException('The specified tag is of type "' . gettype($tag) . '" which can\'t be converted to string.', 1377006656);
        }
        if ($this->connected) {
            $this->collection->deleteMany(
                ['tags' => $tag]
            );
        }
    }

    /**
     * MongoDB has its own GarbageCollector, so nothing more is needed from the CacheManager.
     *
     * see https://docs.MongoDB.com/manual/tutorial/expire-data/
     */
    public function collectGarbage()
    {

    }

    /**
     * Helper method to catch invalid identifiers and tags
     *
     * @param mixed $variable Variable to be checked
     * @return bool
     */
    protected function canBeUsedInStringContext($variable)
    {
        return is_scalar($variable) || (is_object($variable) && method_exists($variable, '__toString'));
    }

    /**
     * @return string
     */
    protected function buildMongoDBUri(): string
    {
        $mongoUri = 'mongodb://' . $this->user . ':' . $this->password;
        $mongoUri .= '@' . $this->hostname . ':' . $this->port;

        return $mongoUri;
    }
}
