<?php

namespace Pdsinterop\Rdf\Flysystem\Adapter;

use ArgumentCountError;
use EasyRdf\Graph as Graph;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Pdsinterop\Rdf\Enum\Format;
use Pdsinterop\Rdf\Flysystem\Exception;
use Pdsinterop\Rdf\Formats;
use Pdsinterop\Rdf\FormatsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdsinterop\Rdf\Flysystem\Adapter\Rdf
 * @covers ::__construct
 * @covers ::<!public>
 *
 * @TODO: All long test names should be replaced by a short name and a @testdox annotation
 */
class RdfTest extends TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private const MOCK_CONTENTS = 'mock contents';
    private const MOCK_CONTENTS_RDF = "@prefix rdfs: <> .\n</> rdfs:comment '' .";
    private const MOCK_MIME = 'mock mime';
    private const MOCK_PATH = '/mock/path';
    private const MOCK_URL = 'mock url';

    /** @var AdapterInterface|MockObject */
    private $mockAdapter;
    /** @var FormatsInterface|MockObject */
    private $mockFormats;
    /** @var Graph|MockObject */
    private $mockGraph;

    private function createAdapter(): Rdf
    {
        $this->mockAdapter = $this->mockAdapter ?? $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->mockGraph = $this->getMockBuilder(Graph::class)->getMock();
        $this->mockFormats = $this->getMockBuilder(FormatsInterface::class)->getMock();

        return new Rdf($this->mockAdapter, $this->mockGraph, $this->mockFormats, self::MOCK_URL);
    }

    ////////////////////////// TESTS WITHOUT FORMATTING \\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::__construct
     */
    public function testRdfAdapterShouldComplainWhenInstantiatedWithoutAdapter(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('0 passed');

        new Rdf();
    }

    /**
     * @covers ::__construct
     */
    public function testRdfAdapterShouldComplainWhenInstantiatedWithoutGraph(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('1 passed');

        $mockAdapter = $this->getMockBuilder(AdapterInterface::class)
            ->getMock()
        ;

        new Rdf($mockAdapter);
    }

    /**
     * @covers ::__construct
     */
    public function testRdfAdapterShouldComplainWhenInstantiatedWithoutFormats(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('2 passed');

        $mockAdapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();

        new Rdf($mockAdapter, $mockGraph);
    }

    /**
     * @covers ::__construct
     */
    public function testRdfAdapterShouldComplainWhenInstantiatedWithoutUrl(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('3 passed');

        $mockAdapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();
        $mockFormats = $this->getMockBuilder(Formats::class)->getMock();

        new Rdf($mockAdapter, $mockGraph, $mockFormats);
    }

    /**
     * @covers ::__construct
     */
    public function testRdfAdapterShouldBeInstantiatedWhenGivenExpectedDependencies(): void
    {
        $this->assertInstanceOf(Rdf::class, $this->createAdapter());
    }

    /**
     * @covers ::copy
     * @covers ::createDir
     * @covers ::delete
     * @covers ::getSize
     * @covers ::deleteDir
     * @covers ::getMetadata
     * @covers ::getTimestamp
     * @covers ::getVisibility
     * @covers ::listContents
     * @covers ::read
     * @covers ::readStream
     * @covers ::rename
     * @covers ::setVisibility
     * @covers ::update
     * @covers ::updateStream
     * @covers ::write
     * @covers ::writeStream
     *
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::getMimeType
     *
     * @dataProvider provideProxyMethods
     */
    public function testRdfAdapterShouldReturnInnerAdapterResultWhenProxyMethodsAreCalled($method, $parameters): void
    {
        $expected = self::MOCK_CONTENTS;

        $count = 1;

        $adapterMethod = $method;

        if ($method === 'read' || $method === 'readStream') {
            $adapterMethod = 'read';
            $expected = ['contents' => $expected];
        } elseif ($method === 'getMetadata' || $method === 'getMimetype') {
            $count = 0;
            $expected = [];
        }

        $adapter = $this->createAdapter();

        if ($method === 'getMetadata' || $method === 'read' || $method === 'readStream') {
            $this->mockAdapter
                ->method('read')
                ->willReturn(['contents' => self::MOCK_CONTENTS])
            ;
        }

        $this->mockAdapter
            ->method('has')
            ->willReturn(false)
        ;

        $this->mockAdapter->expects($this->exactly($count))
            ->method($adapterMethod)
            ->willReturn($expected)
        ;

        $actual = $adapter->{$method}(...$parameters);

        $this->assertSame($expected, $actual);
    }

    //////////////////////////// TESTS WITH FORMATTING \\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::setFormat
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Exception
     *
     * @dataProvider provideUnsupportedFormats
     */
    public function testRdfAdapterShouldComplainWhenAskedToSetUnsupportedFormat($format): void
    {
        $adapter = $this->createAdapter();
        $message = vsprintf($adapter::ERROR_UNSUPPORTED_FORMAT, [$format]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $adapter->setFormat($format);
    }

    /**
     * @covers ::getFormat
     * @covers ::setFormat
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     *
     * @dataProvider provideSupportedFormats
     */
    public function testRdfAdapterShouldSetFormatWhenAskedToSetSupportedFormat($expected): void
    {
        $adapter = $this->createAdapter();

        $adapter->setFormat($expected);

        $actual = $adapter->getFormat();

        $this->assertSame($expected, $actual);
    }

    /**
     * @covers ::getMimeType
     * @covers ::getSize
     * @covers ::has
     * @covers ::read
     * @covers ::readStream
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::getMetadata
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     * @uses \Pdsinterop\Rdf\Formats
     *
     * @dataProvider provideConvertingMethods
     */
    public function testRdfAdapterShouldCallInnerAdapterAndGraphWhenNonProxyMethodsAreCalledWithFormat($method): void
    {
        $formats = [
            Format::JSON_LD,
            Format::N_TRIPLES,
            Format::NOTATION_3,
            Format::RDF_XML,
            Format::TURTLE,
        ];

        $formatCount = count($formats);

        $adapterMethod = $method;

        $adapter = $this->createAdapter();

        if ($method === 'readStream') {
            /*/ The `readStream` method is currently just a proxy for `read` /*/
            $adapterMethod = 'read';
        }

        $this->mockAdapter->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS_RDF])
        ;

        $this->mockGraph->method('serialise')
            ->willReturn('[]')
        ;

        $this->mockFormats->method('getMimeForFormat')
            ->willReturn(self::MOCK_MIME)
        ;

        /*/ This inner adapter method should *never* be called when working with converted (meta)data /*/
        $this->mockAdapter->expects($this->never())->method('getSize');

        /*/ Lets pretend the file exists /*/
        $this->mockAdapter->method('has')->willReturn(true);

        if ($method === 'read' || $method === 'readStream') {
            $this->mockAdapter->expects($this->exactly($formatCount))
                ->method($adapterMethod);
        } elseif (
               $method !== 'getMetadata'
            && $method !== 'getMimeType'
            && $method !== 'getSize'
            && $method !== 'has'
        ) {
            $this->fail('Do not know how to test for ' . $method);
        }

        $expected = [
            'contents' => '[]',
            'mimetype' => self::MOCK_MIME,
            'path' => self::MOCK_PATH,
            'size' => 2,
            'type' => 'file',
            'describedby' => '/mock/path.meta',
            'acl' => '/mock/path.acl',
        ];

        if ($method === 'getMimeType') {
            /*/ Mimetype does not require metadata or read to function.
                Hence, it only returns one value.
            /*/
            $expected = ['mimetype' => self::MOCK_MIME];
        }

        foreach ($formats as $format) {
            $adapter->setFormat($format);

            $actual = $adapter->{$method}(self::MOCK_PATH);

            $this->assertEquals($expected, $actual);
        }
    }

    //////////////////////////// TESTS FOR METADATA \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::getMetadata
     */
    public function testMetaDataShouldNotContainDescribedByWhenCalledForPathWithoutMetaFile(): void
    {
        $adapter = $this->createAdapter();

        $this->mockAdapter->method('has')->willReturn(false);

        $actual = $adapter->getMetadata(self::MOCK_PATH);

        $this->assertArrayNotHasKey('describedby', $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForMetaFileWhenCalled(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $path = self::MOCK_PATH;

        $this->mockAdapter
            ->method('has')
            ->willReturn(true)
        ;

        $actual = $adapter->getMetadata($path);

        $this->assertArrayHasKey('describedby', $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForMetaFileInDirectoryWhenCalledOnFileWithoutMetaFile(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $expected = 'a/longer/path/to/.meta';

        $this->mockAdapter->expects($this->exactly(5))
            ->method('has')
            ->withConsecutive(
                ['a/longer/path/to/file.ext'],
                ['a/longer/path/to/file.ext'],
                ['a/longer/path/to/file.ext.meta'],
                ['a/longer/path/to/.meta'],
            )
            ->willReturnOnConsecutiveCalls(true, true, false, true, true)
        ;

        $metadata = $adapter->getMetadata('a/longer/path/to/file.ext');

        $actual = $metadata['describedby'];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForMetaFileInParentDirectoriesWhenCalledOnFileWithoutMetaFileInCurrentDirectory(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $expected = '.meta';

        $this->mockAdapter->expects($this->exactly(9))
            ->method('has')
            ->withConsecutive(
                ['a/longer/path/to/file.ext'],
                ['a/longer/path/to/file.ext'],
                ['a/longer/path/to/file.ext.meta'],
                ['a/longer/path/to/.meta'],
                ['a/longer/path/.meta'],
                ['a/longer/.meta'],
                ['a/.meta'],
                [$expected],
            )
            ->willReturnOnConsecutiveCalls(
                true,  // 'a/longer/path/to/file.ext'
                true,  // 'a/longer/path/to/file.ext'
                false, // 'a/longer/path/to/file.ext.meta'
                false, // 'a/longer/path/to/.meta'
                false, // 'a/longer/path/.meta'
                false, // 'a/longer/.meta'
                false, // 'a/.meta'
                true,  // '.meta'
                true);

        $metadata = $adapter->getMetadata('a/longer/path/to/file.ext');

        $actual = $metadata['describedby'];

        $this->assertEquals($expected, $actual);
    }

    // @TODO: Add test to prove `getMetadata` is not called for a `has` lookup

    // @TODO: Add test to document behaviour for metadata calls for $paths with nd without leading slash `/`

    /////////////////////////////// TESTS FOR ACL \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * @covers ::getMetadata
     */
    public function testMetaDataShouldNotContainAclWhenCalledForPathWithoutAclFile(): void
    {
        $adapter = $this->createAdapter();

        $this->mockAdapter->method('has')->willReturn(false);

        $actual = $adapter->getMetadata(self::MOCK_PATH);

        $this->assertArrayNotHasKey('acl', $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForAclFileWhenCalled(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $path = self::MOCK_PATH;

        $this->mockAdapter
            ->method('has')
            ->willReturn(true)
        ;

        $actual = $adapter->getMetadata($path);

        $this->assertArrayHasKey('acl', $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForAclFileInDirectoryWhenCalledOnFileWithoutAclFile(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $expected = '/a/longer/path/to/.acl';


        $this->mockAdapter->expects($this->exactly(5))
            ->method('has')
            ->withConsecutive(
                ['/a/longer/path/to/file.ext'],
                ['/a/longer/path/to/file.ext'],
                ['/a/longer/path/to/file.ext.meta'],
                ['/a/longer/path/to/file.ext.acl'],
                [$expected],
            )
            ->willReturnOnConsecutiveCalls(true, true, true, false, true)
        ;

        $metadata = $adapter->getMetadata('/a/longer/path/to/file.ext');

        $actual = $metadata['acl'];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::getMetadata
     *
     * @uses \Pdsinterop\Rdf\Enum\Format
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::read
     * @uses \Pdsinterop\Rdf\Flysystem\Adapter\Rdf::setFormat
     */
    public function testMetaDataShouldLookForAclFileInParentDirectoriesWhenCalledOnFileWithoutAclFileInCurrentDirectory(): void
    {
        $adapter = $this->createAdapter();

        /*/ This part of the test is only needed for conversion /*/
        $adapter->setFormat('jsonld');

        $this->mockAdapter
            ->method('read')
            ->willReturn(['contents' => self::MOCK_CONTENTS])
        ;

        /*/ This part is always needed /*/
        $expected = '.acl';

        $this->mockAdapter->expects($this->exactly(9))
            ->method('has')
            ->withConsecutive(
                ['/a/longer/path/to/file.ext'],
                ['/a/longer/path/to/file.ext'],
                ['/a/longer/path/to/file.ext.meta'],
                ['/a/longer/path/to/file.ext.acl'],
                ['/a/longer/path/to/.acl'],
                ['/a/longer/path/.acl'],
                ['/a/longer/.acl'],
                ['/a/.acl'],
                [$expected],
            )
            ->willReturnOnConsecutiveCalls(true, true, true, false, false, false, false, false, true)
        ;

        $metadata = $adapter->getMetadata('/a/longer/path/to/file.ext');

        $actual = $metadata['acl'];

        $this->assertEquals($expected, $actual);
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function provideProxyMethods(): array
    {
        $mockConfig = $this->getMockBuilder(Config::class)->getMock();
        $mockContents = self::MOCK_CONTENTS;
        $mockPath = self::MOCK_PATH;
        $mockResource = fopen('php://temp', 'rb');

        return [
            'copy' => ['copy', [$mockPath, $mockPath]],
            'createDir' => ['createDir', [$mockPath, $mockConfig]],
            'delete' => ['delete', [$mockPath]],
            'deleteDir' => ['deleteDir', [$mockPath]],
            'getMetadata' => ['getMetadata', [$mockPath]],
            'getMimetype' => ['getMimetype', [$mockPath]],
            'getSize' => ['getSize', [$mockPath]],
            'getVisibility' => ['getVisibility', [$mockPath]],
            'getTimestamp' => ['getTimestamp', [$mockPath]],
            'listContents' => ['listContents', []],
            'read' => ['read', [$mockPath]],
            'readStream' => ['readStream', [$mockPath]],
            'rename' => ['rename', [$mockPath, $mockPath]],
            'setVisibility' => ['setVisibility', [$mockPath, 'mock visibility']],
            'update' => ['update', [$mockPath, $mockContents, $mockConfig]],
            'updateStream' => ['updateStream', [$mockPath, $mockResource, $mockConfig]],
            'write' => ['write', [$mockPath, $mockContents, $mockConfig]],
            'writeStream' => ['writeStream', [$mockPath, $mockResource, $mockConfig]],
        ];
    }

    public function provideConvertingMethods(): array
    {
        return [
            'getMetadata' => ['getMetadata'],
            'getSize' => ['getSize'],
            'has' => ['has'],
            'getMimeType' => ['getMimeType'],
            'read' => ['read'],
            'readStream' => ['readStream'],
        ];
    }

    public function provideSupportedFormats(): array
    {
        return [
            'string: empty' => [''],
            Format::JSON_LD => [Format::JSON_LD],
            Format::N_TRIPLES => [Format::N_TRIPLES],
            Format::NOTATION_3 => [Format::NOTATION_3],
            Format::RDF_XML => [Format::RDF_XML],
            Format::TURTLE => [Format::TURTLE],
        ];
    }

    public function provideUnsupportedFormats(): array
    {
        return [
            'mock format' => ['mock format'],
            Format::UNKNOWN => [Format::UNKNOWN],
        ];
    }
}
