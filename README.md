# TYPO3 MongoDB cache backend

With this cache backend you can store and use data in MongoDB in TYPO3 via the cache manager.

# Introduction
The whole thing is a proof of concept and was not tested in production. It was a test of the ddev possibilities and the TYPO3 Cache API in version 11.4. This repo merely documents the knowledge that has been built up.

It is based on the RedisBackend of TYPO3.

# Preparations

- ddev must first be supplemented with a docker compose for MongoDB. There is a yaml file in Resources/Private for this purpose. MongoDB Express can then be accessed via https://projectname.ddev.site:8081
- In order to access MongoDB in PHP, the .ddev/config.yaml file must be extended. Required is: `webimage_extra_packages: [php7.4-mongodb]` Just pay attention to the PHP version
- First you have to create a database via the MongoDB web interface. I have named this "typo3"
- After that, you still need a collection. I have called this "cache".

# Cache Lifetime
If you want to use the lifetime, MongoDB must be prepared accordingly:
https://docs.mongodb.com/manual/tutorial/expire-data/#expire-documents-after-a-specified-number-of-seconds

The index must then refer to the field "lifetime":
`db.log_events.createIndex( { "lifetime": 1 }, { expireAfterSeconds: 3600 } )`

The cache backend then sets the lifetime and the entry ends at this point.

# Use in a TYPO3 extension (ext_localconf.php)

### Standard TYPO3 cache
```
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'] = [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['backend'] = \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class;
}
```

### Mongo DB cache - Based on the default values
```
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'] = [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['backend'] = \Ayacoo\MongoDb\Cache\Backend\MongoDBBackend::class;
}
```

### Mongo DB cache - with all custom options
```
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache'] = [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['backend'] = \Ayacoo\MongoDb\Cache\Backend\MongoDBBackend::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['my_cache']['options'] = [
        'databaseName' => 'customDatabase',
        'collectionName' => 'customCache',
        'port' => 1234,
        'hostname' => '',
        'user' => '',
        'password' => '',
    ];
}
```

Note: MongoDB is so smart that the collection and the database, if non-existent, are created immediately.

# More documentation

https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/CachingFramework/Configuration/Index.html
