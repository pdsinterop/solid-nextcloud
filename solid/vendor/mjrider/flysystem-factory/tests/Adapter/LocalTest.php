<?php

namespace MJRider\FlysystemFactory\Adapter;

use PHPUnit\Framework\TestCase;

class LocalTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = 'local:' . __DIR__ . '/files/';
        is_dir(__DIR__ . '/files/') || mkdir(__DIR__ . '/files/');
    }

    public function testLocal()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Adapter\Local', $filesystem->getAdapter());
    }
}
