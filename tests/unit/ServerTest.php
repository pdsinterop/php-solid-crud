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
 * @uses \Laminas\Diactoros\Response
 * @uses \Laminas\Diactoros\ServerRequest
 * @uses \Pdsinterop\Solid\Resources\Exception
 * @uses \Pdsinterop\Solid\Resources\Server
 */
class ServerTest extends TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    const MOCK_SLUG = 'Mock Slug';

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    const MOCK_BODY = 'php://temp';
    const MOCK_URL = '';
    const MOCK_SERVER_PARAMS = [];
    const MOCK_UPLOADED_FILES = [];

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
     * @dataProvider provideMimeTypes
     */
    public function testRespondToPOSTCreateRequest($mimetype)
    {
        $expected = self::MOCK_SLUG . self::MOCK_SLUG;
        $extensions = [
            'application/json' => '.json',
            'application/ld+json' => '.json',
            'text/html' => '.html',
            'text/plain' => '.txt',
            'text/turtle' => '.ttl',
        ];

        if (
            $mimetype === 'application/ld+json'
            || $mimetype === 'text/turtle'
            || $mimetype === 'text/html'
            || $mimetype === 'text/plain'
            || $mimetype === 'application/json'
        ) {
            /*/ If the filename suggestion in the Slug contains a file extension, another file extension is appended for known/supported MIME types. This leads to a filename with two file extensions, like 'example.ttl.ttl'.

            If the MIME type is not known or does not match the provided file extension, there are still two file extensions. They are merely not the same.

            For instance 'example.json.ttl' or 'example.ttl.json'.
             /*/
            $expected .= $extensions[$mimetype];
        }

        // Arrange
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockGraph = $this->getMockBuilder(Graph::class)->getMock();
        $request = $this->createRequest('POST', [
            'Content-Type' => $mimetype,
            'Link' => '',
            'Slug' => self::MOCK_SLUG,
        ]);

        $mockFileSystem
            ->method('has')
            ->withAnyParameters()
            ->willReturnMap([
                [self::MOCK_SLUG, true],
                [$expected, false],
            ]);

        $mockFileSystem
            ->method('getMimetype')
            ->with(self::MOCK_SLUG)
            ->willReturn(Server::MIME_TYPE_DIRECTORY);

        $mockFileSystem
            ->method('write')
            ->with($expected, '', [])
            ->willReturn(true);

        // Act
        $server = new Server($mockFileSystem, new Response(), $mockGraph);
        $response = $server->respondToRequest($request);

        // Assert
        $actual = $response->getHeaderLine('Location');

        $this->assertEquals($expected, $actual);
    }

    /////////////////////////////// DATAPROVIDERS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public static function provideMimeTypes()
    {
        return [
            'mime: (empty)' => [''],
            'mime: application/json' => ['application/json'],
            'mime: application/ld+json' => ['application/ld+json'],
            'mime: some/other' => ['some/other'],
            'mime: text/html' => ['text/html'],
            'mime: text/plain' => ['text/plain'],
            'mime: text/turtle' => ['text/turtle'],
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
