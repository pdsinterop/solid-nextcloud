<?php

namespace MJRider\FlysystemFactory\Adapter;

use PHPUnit\Framework\TestCase;

class NullTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = 'null:/';
    }

    public function testNull()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\League\Flysystem\Adapter\NullAdapter', $filesystem->getAdapter());
    }
}
