<?php
/**
 * Unit Test for the Server class
 */

namespace Pdsinterop\Solid\Resources;

use ArgumentCountError;
use EasyRdf\Graph;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @covers \Pdsinterop\Solid\Resources\Server
 * @coversDefaultClass \Pdsinterop\Solid\Resources\Server
 *
 * @uses   \Laminas\Diactoros\Response
 * @uses   \Laminas\Diactoros\ServerRequest
 * @uses   \Pdsinterop\Solid\Resources\Exception
 * @uses   \Pdsinterop\Solid\Resources\Server
 */
class ServerTest extends TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    const MOCK_BODY = 'php://temp';
    const MOCK_PATH = '/path/to/resource/';
    const MOCK_SERVER_PARAMS = [];
    const MOCK_UPLOADED_FILES = [];
    const MOCK_URL = 'https://example.com' . self::MOCK_PATH;

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

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

    /**
     * @testdox Server should complain when asked to respond to a request without a Request
     *
     * @covers ::respondToRequest
     */
    public function testRespondToRequestWithoutRequest()
    {
        // Arrange
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();

        $server = new Server($mockFileSystem, $mockResponse, $mockGraph);

        // Assert
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        // Act
        $server->respondToRequest();
    }

    /**
     * @testdox Server should complain when asked to respond to a Request with an unsupported HTTP METHOD
     *
     * @covers ::respondToRequest
     *
     * @dataProvider provideUnsupportedHttpMethods
     */
    public function testRespondToRequestWithUnsupportedHttpMethod($httpMethod)
    {
        // Arrange
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();
        $request = $this->createRequest($httpMethod);

        $mockResponse = new Response();

        $server = new Server($mockFileSystem, $mockResponse, $mockGraph);

        // Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown or unsupported HTTP METHOD');

        // Act
        $server->respondToRequest($request);
    }

    /**
     * @testdox Server should create a resource when asked to create a resource with Slug header present
     *
     * @covers ::respondToRequest
     *
     * @dataProvider provideSlugs
     */
    public function testRespondToPOSTCreateRequest($slug, $mimetype, $expected)
    {
        // Arrange
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();
        $request = $this->createRequest('POST', [
            'Content-Type' => $mimetype,
            'Link' => '',
            'Slug' => $slug,
        ]);

        $mockFileSystem
            ->method('has')
            ->withAnyParameters()
            ->willReturnMap([
                [self::MOCK_PATH, true],
            ]);

        $mockFileSystem
            ->method('getMimetype')
            ->with(self::MOCK_PATH)
            ->willReturn(Server::MIME_TYPE_DIRECTORY);

        $mockFileSystem
            ->method('write')
            ->withAnyParameters()
            ->willReturn(true);

        // Act
        $server = new Server($mockFileSystem, new Response(), $mockGraph);
        $response = $server->respondToRequest($request);

        // Assert
        $actual = $response->getHeaderLine('Location');

        $this->assertEquals(self::MOCK_PATH . $expected, $actual);
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public static function provideSlugs()
    {
        return [
            // '' => [$slug, $mimetype, $expectedFilename],
            'Slug with json extension, with ld+json MIME' => ['Mock Slug.json', 'application/ld+json', 'Mock Slug.json'],
            'Slug with jsonld extension, with ld+json MIME' => ['Mock Slug.jsonld', 'application/ld+json', 'Mock Slug.jsonld'],
            'Slug with PNG extension, with PNG MIME' => ['Mock Slug.png', 'image/png', 'Mock Slug.png'],
            'Slug with some other, extension) with Turtle MIME' => ['Mock Slug.other', 'text/turtle', 'Mock Slug.other'],
            'Slug with Turtle extension, with other MIME' => ['Mock Slug.ttl', 'some/other', 'Mock Slug.ttl'],
            'Slug with Turtle extension, with Turtle MIME' => ['Mock Slug.ttl', 'text/turtle', 'Mock Slug.ttl'],
            'Slug without extension, with some other  MIME' => ['Mock Slug', 'some/other', 'Mock Slug'],
            'Slug without extension, with turtle MIME' => ['Mock Slug', 'text/turtle', 'Mock Slug'],
        ];
    }

    public static function provideUnsupportedHttpMethods()
    {
        return [
            'string:CONNECT' => ['CONNECT'],
            'string:TRACE' => ['TRACE'],
            'string:UNKNOWN' => ['UNKNOWN'],
        ];
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    private function createRequest(string $httpMethod, array $headers = []): ServerRequestInterface
    {
        return new ServerRequest(
            self::MOCK_SERVER_PARAMS,
            self::MOCK_UPLOADED_FILES,
            self::MOCK_URL,
            $httpMethod,
            self::MOCK_BODY,
            $headers
        );
    }
}
