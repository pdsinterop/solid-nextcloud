<?php

namespace Pdsinterop\Rdf\Flysystem\Plugin;

use ArgumentCountError;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Pdsinterop\Rdf\Flysystem\Adapter\RdfAdapterInterface;
use Pdsinterop\Rdf\Flysystem\Exception;
use Pdsinterop\Rdf\FormatsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @coversDefaultClass \Pdsinterop\Rdf\Flysystem\Plugin\AsMime
 * @covers ::__construct
 * @covers ::<!public>
 */
class AsMimeTest extends TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private const MOCK_MIME = 'mock mime';

    private function createPlugin() : AsMime
    {
        $mockFormats = $this->createMockFormats();

        $mockFilesystem = $this->createMockFilesystem();

        $plugin = new AsMime($mockFormats);

        $plugin->setFilesystem($mockFilesystem);

        return $plugin;
    }

    ////////////////////////////// CUSTOM ASSERTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    private function assertPropertyEquals($object, string $property, $expected): void
    {
        $reflector = new ReflectionObject($object);

        $attribute = $reflector->getProperty($property);
        $attribute->setAccessible(true);

        $actual = $attribute->getValue($object);

        $this->assertSame($expected, $actual);
    }

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::__construct
     */
    public function testPluginShouldComplainWhenInstantiatedWithoutGraph(): void
    {
        $this->expectException(ArgumentCountError::class);

        new AsMime();
    }

    /**
     * @covers ::__construct
     */
    public function testPluginShouldReceiveEasyRdfGraphWhenInstantiated(): void
    {
        $mockFormats = $this->createMockFormats();

        $actual = new AsMime($mockFormats);

        $this->assertInstanceOf(AsMime::class, $actual);
    }

    /**
     * @covers ::setFilesystem
     */
    public function testPluginShouldComplainWhenSetFilesystemCalledWithoutFilesystem(): void
    {
        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);

        $this->expectException(ArgumentCountError::class);

        $plugin->setFilesystem();
    }

    /**
     * @covers ::setFilesystem
     */
    public function testPluginShouldContainFilesystemWhenFilesystemGiven(): void
    {
        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);

        $expected = $this->createMockFilesystem();

        $plugin->setFilesystem($expected);

        $this->assertPropertyEquals($plugin, 'filesystem', $expected);
    }

    /**
     * @covers ::getMethod
     */
    public function testPluginShouldReturnExpectedMethodNameWhenAskedForMethod(): void
    {
        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);

        $expected = 'asMime';

        $actual = $plugin->getMethod();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::handle
     *
     * @uses \Pdsinterop\Rdf\Flysystem\Exception::create
     */
    public function testPluginShouldComplainWhenHandleCalledWithoutFilesystem(): void
    {
        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('can not be used before an adapter has been added to the filesystem');

        $plugin->handle('');
    }

    /**
     * @covers ::handle
     */
    public function testPluginShouldComplainWhenHandleCalledWithoutMimetype(): void
    {
        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments to function Pdsinterop\Rdf\Flysystem\Plugin\AsMime::handle(), 0 passed');

        $plugin->handle();
    }

    /**
     * @covers ::handle
     */
    public function testPluginShouldReturnFileSystemWhenHandleCalled(): void
    {
        $expected = $this->createMockFilesystem();

        $mockFormats = $this->createMockFormats();

        $plugin = new AsMime($mockFormats);
        $plugin->setFilesystem($expected);

        $this->assertPropertyEquals($plugin, 'filesystem', $expected);

        $actual = $plugin->handle(self::MOCK_MIME);

        $this->assertSame($expected, $actual);
    }

    /**
     * @covers ::handle
     */
    public function testPluginShouldNotSetFormatWhenAdapterIsNotRdfAdapter(): void
    {
        $plugin = $this->createPlugin();

        $this->createMockFormats()->expects($this->never())
            ->method('getFormatForMime')
        ;

        $plugin->handle(self::MOCK_MIME);
    }

    /**
     * @covers ::handle
     */
    public function testPluginShouldSetFormatWhenAdapterIsRdfAdapter(): void
    {
        $mockFormats = $this->createMockFormats();

        $mockAdapter = $this->getMockBuilder(RdfAdapterInterface::class)->getMock();

        $mockAdapter->expects($this->once())
            ->method('setFormat')
        ;

        $mockFilesystem = $this->createMockFilesystem($mockAdapter);

        $plugin = new AsMime($mockFormats);
        $plugin->setFilesystem($mockFilesystem);

        $plugin->handle(self::MOCK_MIME);
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @return FilesystemInterface | MockObject
     */
    private function createMockFilesystem($mockAdapter = null): FilesystemInterface
    {
        $mockFilesystem = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($mockAdapter === null) {
            $mockAdapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        }

        $mockFilesystem->method('getAdapter')
            ->willReturn($mockAdapter)
        ;

        return $mockFilesystem;
    }

    /**
     * @return FormatsInterface | MockObject
     */
    private function createMockFormats()
    {
        return $this->getMockBuilder(FormatsInterface::class)
            ->getMock();
    }
}
