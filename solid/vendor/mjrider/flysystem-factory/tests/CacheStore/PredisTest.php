<?php

namespace MJRider\FlysystemFactory\CacheStore;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PredisTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = 'null:/';
        // FIXME: getenv redis endpoint instead of hardcoding a empty url
    }

    public function testPredis()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache('predis:', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Cached\CachedAdapter', $filesystem->getAdapter());
        $this->assertInstanceOf('\League\Flysystem\Cached\Storage\Predis', $filesystem->getAdapter()->getCache());
    }

    public function testPredisTcp()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache('predis-tcp:', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Cached\CachedAdapter', $filesystem->getAdapter());
        $this->assertInstanceOf('\League\Flysystem\Cached\Storage\Predis', $filesystem->getAdapter()->getCache());
    }

    public function testPredisProperties()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache('predis-tcp:?cachekey=foobar&expire=3767', $filesystem);
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
