<?php

namespace MJRider\FlysystemFactory\CacheStore;

use PHPUnit\Framework\TestCase;

class MemoryTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = 'null:/';
    }

    public function testMemory()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $filesystem = \MJRider\FlysystemFactory\cache('memory:', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Cached\CachedAdapter', $filesystem->getAdapter());
        $this->assertInstanceOf('\League\Flysystem\Cached\Storage\Memory', $filesystem->getAdapter()->getCache());
    }
}
