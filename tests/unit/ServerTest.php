<?php
/**
 * Unit Test for the Server class
 */
namespace Pdsinterop\Solid\Resources;

use ArgumentCountError;
use EasyRdf\Graph;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversDefaultClass \Pdsinterop\Solid\Resources\Server
 * @covers ::__construct
 */
class ServerTest extends TestCase
{
    /** @testdox Server should complain when instantiated without File System */
    public function testInstatiationWithoutFileSystem()
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        new Server();
    }

    /** @testdox Server should complain when instantiated without Response */
    public function testInstatiationWithoutResponse()
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 1 passed/');

        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();

        new Server($mockFileSystem);
    }

    /** @testdox Server should be instantiated when constructed without Graph */
    public function testInstatiationWithoutGraph()
    {
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $actual = new Server($mockFileSystem, $mockResponse);
        $expected = Server::class;

        $this->assertInstanceOf($expected, $actual);
    }

    /** @testdox Server should be instantiated when constructed with Graph */
    public function testInstatiationWithGraph()
    {
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();

        $actual = new Server($mockFileSystem, $mockResponse, $mockGraph);
        $expected = Server::class;

        $this->assertInstanceOf($expected, $actual);
    }
}
