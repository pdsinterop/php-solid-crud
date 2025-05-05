<?php

namespace Pdsinterop\Solid\Resources;

use League\Flysystem\FilesystemInterface;
use Pdsinterop\Rdf\Flysystem\Plugin\AsMime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use TypeError;

/**
 * @coversDefaultClass \Pdsinterop\Solid\Resources\Server
 * @covers \Pdsinterop\Solid\Resources\Server
 */
class ServerTest extends TestCase
{
    ////////////////////////////////// FIXTURES \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    const MOCK_HTTP_METHOD = 'MOCK';
    const MOCK_PATH = '/mock/path';

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

    /**
     * @testdox Server should complain when instantiated without a filesystem
     * @covers ::__construct
     */
    public function testServerConstructWithoutFilesystem()
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        new Server();
    }

    /**
     * @testdox Server should complain when instantiated without a response
     * @covers ::__construct
     */
    public function testServerConstructWithoutResponse()
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 1 passed/');

        $mockFilesystem = $this->createMock(FilesystemInterface::class);

        new Server($mockFilesystem);
    }

    /**
     * @testdox Server should be instantiated when given a filesystem and a response
     * @covers ::__construct
     */
    public function testServerConstructWithFilesystemAndResponse()
    {
        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $server = new Server($mockFilesystem, $mockResponse);

        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * @testdox Server should complain when asked to RespondToRequest without a request
     * @covers ::respondToRequest
     */
    public function testServerRespondToRequestWithoutRequest()
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Too few arguments .+ 0 passed/');

        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);

        $server = new Server($mockFilesystem, $mockResponse);

        $server->respondToRequest();
    }

    /**
     * @testdox Server should complain when asked to RespondToRequest with a request with an unknown HTTP method
     *
     * @covers ::respondToRequest
     *
     * @uses \Pdsinterop\Solid\Resources\Exception
     */
    public function testServerRespondToRequestWithUnknownHttpMethod()
    {
        // Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(vsprintf(Server::ERROR_UNKNOWN_HTTP_METHOD, [self::MOCK_HTTP_METHOD]));

        // Arrange
        $mockRequest = $this->createMockRequest();
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockFilesystem = $this->createMockFilesystem();

        //Act
        $server = new Server($mockFilesystem, $mockResponse);
        $server->respondToRequest($mockRequest);
    }

    /**
     * @testdox Server should return provided response when asked to RespondToRequest with va   lid request
     *
     * @covers ::respondToRequest
     */
    public function testServerRespondToRequestWithRequest()
    {
        // Arrange
        $mockFilesystem = $this->createMockFilesystem();
        $mockRequest = $this->createMockRequest('GET');
        $expected = $this->createMockResponse();

        // Act
        $server = new Server($mockFilesystem, $expected);
        $actual = $server->respondToRequest($mockRequest);

        // Assert
        $this->assertSame($expected, $actual);
    }

    ////////////////////////////// MOCKS AND STUBS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function createMockFilesystem(): FilesystemInterface|MockObject
    {
        $mockFilesystem = $this->getMockBuilder(FilesystemInterface::class)
            ->onlyMethods([
                'addPlugin', 'copy', 'createDir', 'delete', 'deleteDir', 'get', 'getMetadata', 'getMimetype', 'getSize', 'getTimestamp', 'getVisibility', 'has', 'listContents', 'put', 'putStream', 'read', 'readAndDelete', 'readStream', 'rename', 'setVisibility', 'update', 'updateStream', 'write', 'writeStream'
            ])
            ->addMethods(['asMime'])
            ->getMock();

        $mockAsMime = $this->getMockBuilder(AsMime::class)
            // ->onlyMethods(['getMimetype', 'getSize', 'getTimestamp'])
            ->addMethods(['has'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockFilesystem->method('asMime')->willReturn($mockAsMime);

        return $mockFilesystem;
    }

    public function createMockRequest($httpMethod = self::MOCK_HTTP_METHOD): ServerRequestInterface|MockObject
    {
        $mockRequest = $this->createMock(ServerRequestInterface::class);

        $mockUri = $this->createMock(UriInterface::class);
        $mockUri->method('getPath')->willReturn(self::MOCK_PATH);

        $mockBody = $this->createMock(StreamInterface::class);

        $mockRequest->method('getUri')->willReturn($mockUri);
        $mockRequest->method('getQueryParams')->willReturn([]);
        $mockRequest->method('getMethod')->willReturn($httpMethod);
        $mockRequest->method('getBody')->willReturn($mockBody);
        // $mockRequest->method('getMethod')->willReturn('GET');
        $mockRequest->method('getHeaderLine')->willReturn('');

        return $mockRequest;
    }

    public function createMockResponse(): ResponseInterface|MockObject
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockBody = $this->createMock(StreamInterface::class);

        $mockResponse->method('getBody')->willReturn($mockBody);
        $mockResponse->method('withStatus')->willReturnSelf();

        return $mockResponse;
    }
}
