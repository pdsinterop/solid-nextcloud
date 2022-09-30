# Examples

The boilerplate code needed to get a flysystem running:
```php
$location = getenv('STORAGE_ENDPOINT');
$cache = getenv('STORAGE_CACHE_ENDPOINT');

$filesystem = \MJRider\FlysystemFactory\create($location);
if ($cache!==false) {
    $filesystem = \MJRider\FlysystemFactory\cache($cache, $filesystem);
}
```

the only settings are in the url formated settings for endpoint and cache

## Adapters
### S3
Aws:
`s3://accesstoken:secretkey@region/bucketname`

Minio:
`s3://acccesstoken:secretkey@fakeregion/bucketname?endpoint=http://locationofminio:port`

### B2
`b2://exampleuser:examplekey@bucket/`  
no futher settings are available

### Local
`local:/path/to/folder`

### FTP
`ftp://user:pass@host:port/path/to/folder?ssl=false&passive=false&timeout=30`

## CacheStores

### Memcached
`memcached://host:port/`  
Connect to memcached running on host:port 

options:
expire: ttl of the cache
cachekey: under what key to store the cache

### Memory
`memory:`  
No futher settings

### Predis
`predis:`  
redis on localhost, all defaults:

`predis-tcp://10.20.30.40/?expire=3600`  
redis on host 10.20.30.40 with an expiry of 3600

options:
expire: ttl of the cache
cachekey: under what key to store the cache

predis-tcp and predis-unix urls are passed as tcp:// or unix:// to the redis client
