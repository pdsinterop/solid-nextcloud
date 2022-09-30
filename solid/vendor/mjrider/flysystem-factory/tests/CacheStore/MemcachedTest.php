<?php

namespace MJRider\FlysystemFactory\CacheStore;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @requires extension memcached
 */
class MemcachedTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = 'null:/';
        // FIXME: getenv memcached endpoint instead of hardcoding a empty url
    }

    public function testMemcached()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache('memcached://127.0.0.1:11211/', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Cached\CachedAdapter', $filesystem->getAdapter());
        $this->assertInstanceOf('\League\Flysystem\Cached\Storage\Memcached', $filesystem->getAdapter()->getCache());
    }

    public function testMemcachedProperties()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache(
            'memcached://127.0.0.1:11211/?cachekey=foobar&expire=3767',
            $filesystem
        );
        $cache = $filesystem->getAdapter()->getCache();

        $reflCache = new ReflectionClass($cache);

        $reflkey = $reflCache->getProperty('key');
        $reflkey->setAccessible(true);

        $reflexpire = $reflCache->getProperty('expire');
        $reflexpire->setAccessible(true);

        $this->assertEquals('foobar', $reflkey->getValue($cache));
        $this->assertEquals(3767, $reflexpire->getValue($cache));
    }
}
