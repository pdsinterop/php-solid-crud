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
use Pdsinterop\Rdf\Flysystem\Plugin\AsMime;
use PHPUnit\Framework\MockObject\MockObject;
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
    const MOCK_HTTP_METHOD = 'MOCK';
    const MOCK_PATH = '/mock/path/';
    const MOCK_SERVER_PARAMS = [];
    const MOCK_UPLOADED_FILES = [];
    const MOCK_URL = 'https://example.com' . self::MOCK_PATH;

    public static function setUpBeforeClass(): void
    {
        $phpUnitVersion = \PHPUnit\Runner\Version::id();

        /* PHP 8.4.0 and PHPUnit 9 triggers a Deprecation Warning, which PHPUnit
         * promotes to an Exception, which causes tests to fail.This is fixed in
         * PHPUnit v10. As a workaround for v9, instead of loading the real
         * interface, a fixed interface is loaded on the fly.
         */
        if (
            version_compare(PHP_VERSION, '8.4.0', '>=')
            && version_compare($phpUnitVersion, '9.0.0', '>=')
            && version_compare($phpUnitVersion, '10.0.0', '<')
        ) {
            $file = __DIR__ . '/../../vendor/league/flysystem/src/FilesystemInterface.php';
            $contents = file_get_contents($file);
            $contents = str_replace(['<?php','Handler $handler = null'], ['','?Handler $handler = null'], $contents);
            eval($contents);
        }
    }

    /////////////////////////////////// TESTS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    /** @testdox Server should complain when instantiated without File System */
    public function testServerInstatiationWithoutFileSystem()
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        new Server();
    }

    /** @testdox Server should complain when instantiated without Response */
    public function testServerInstatiationWithoutResponse()
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 1 passed/');

        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();

        new Server($mockFileSystem);
    }

    /** @testdox Server should be instantiated when constructed without Graph */
    public function testServerInstatiationWithoutGraph()
    {
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();

        $actual = new Server($mockFileSystem, $mockResponse);
        $expected = Server::class;

        $this->assertInstanceOf($expected, $actual);
    }

    /** @testdox Server should be instantiated when constructed with Graph */
    public function testServerInstatiationWithGraph()
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
    public function testServerRespondToRequestWithoutRequest()
    {
        // Arrange
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();
        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();

        // Assert
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        // Act
        $server = new Server($mockFileSystem, $mockResponse);
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
        $mockResponse = new Response();
        $request = $this->createRequest($httpMethod);

        // Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(vsprintf(Server::ERROR_UNKNOWN_HTTP_METHOD, [$httpMethod]));

        // Act
        $server = new Server($mockFileSystem, $mockResponse);
        $server->respondToRequest($request);
    }

    /**
     * @testdox Server should return response when asked to RespondToRequest with valid request
     *
     * @covers ::respondToRequest
     */
    public function testServerRespondToRequestWithRequest()
    {
        // Arrange
        $mockFileSystem = $this->createMockFileSystem();
        $request = $this->createRequest('GET');

        // Act
        $server = new Server($mockFileSystem, new Response());
        $response = $server->respondToRequest($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
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
            'Slug with jsonld extension, with ld+json MIME)' => ['Mock Slug.jsonld', 'application/ld+json', 'Mock Slug.jsonld.json'],
            'Slug with PNG extension, with PNG MIME' => ['Mock Slug.png', 'image/png', 'Mock Slug.png'],
            'Slug with some other, extension) with Turtle MIME' => ['Mock Slug.other', 'text/turtle', 'Mock Slug.other.ttl'],
            'Slug with Turtle extension, with other MIME' => ['Mock Slug.ttl', 'some/other', 'Mock Slug.ttl'],
            'Slug with Turtle extension, with Turtle MIME' => ['Mock Slug.ttl', 'text/turtle', 'Mock Slug.ttl'],
            'Slug without extension), with some other  MIME' => ['Mock Slug', 'some/other', 'Mock Slug'],
            'Slug without extension), with turtle MIME' => ['Mock Slug', 'text/turtle', 'Mock Slug.ttl'],
        ];
    }

    public static function provideUnsupportedHttpMethods()
    {
        return [
            'string:CONNECT' => ['CONNECT'],
            'string:TRACE' => ['TRACE'],
            'string:UNKNOWN' => [self::MOCK_HTTP_METHOD],
        ];
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function createMockFileSystem(): FilesystemInterface|MockObject
    {
        $mockFileSystem = $this->getMockBuilder(FilesystemInterface::class)
            ->onlyMethods([
                'addPlugin', 'copy', 'createDir', 'delete', 'deleteDir', 'get', 'getMetadata', 'getMimetype', 'getSize', 'getTimestamp', 'getVisibility', 'has', 'listContents', 'put', 'putStream', 'read', 'readAndDelete', 'readStream', 'rename', 'setVisibility', 'update', 'updateStream', 'write', 'writeStream'
            ])
            ->addMethods(['asMime'])
            ->getMock();

        $mockAsMime = $this->getMockBuilder(AsMime::class)
            // ->onlyMethods(['getMimetype', 'getSize', 'getTimestamp'])
            ->addMethods(['has', 'getMimetype', 'read'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockAsMime->method('getMimetype')->willReturn('text/turtle');
        $mockAsMime->method('has')->willReturn(true);
        $mockAsMime->method('read')->willReturn('');

        $mockFileSystem->method('asMime')->willReturn($mockAsMime);

        return $mockFileSystem;
    }

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
