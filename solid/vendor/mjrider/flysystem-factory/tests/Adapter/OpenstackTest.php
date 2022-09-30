<?php

namespace MJRider\FlysystemFactory\Adapter;

use PHPUnit\Framework\TestCase;

class OpenstackTest extends TestCase
{
    protected $root = '';

    public function setUp(): void
    {
        $this->root = getenv('TEST_OPENSTACK_LOCATION');
        if ($this->root === false) {
            $this->markTestSkipped('no openstack endpoint available, test skipped');
        }
    }

    public function testOpenStack()
    {
        $filesystem = \MJRider\FlysystemFactory\create($this->root);
        $this->assertInstanceOf('\League\Flysystem\Filesystem', $filesystem);
        $this->assertInstanceOf('\Nimbusoft\Flysystem\OpenStack\SwiftAdapter', $filesystem->getAdapter());
    }
}
