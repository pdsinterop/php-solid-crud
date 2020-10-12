<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Resources;

use League\Flysystem\FilesystemInterface as Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Server
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public const ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER = 'Only empty containers can be deleted, "%s" is not empty';
    public const ERROR_NOT_IMPLEMENTED_SPARQL = 'SPARQL Not Implemented';
    public const ERROR_PATH_DOES_NOT_EXIST = 'Requested path "%s" does not exist';
    public const ERROR_POST_EXISTING_RESOURCE = 'Requested path "%s" already exists. Can not "POST" to existing resource. Use "PUT" instead';
    public const ERROR_PUT_NON_EXISTING_RESOURCE = self::ERROR_PATH_DOES_NOT_EXIST . '. Can not "PUT" non-existing resource. Use "POST" instead';
    public const ERROR_UNKNOWN_HTTP_METHOD = 'Unknown or unsupported HTTP METHOD "%s"';

    private const MIME_TYPE_DIRECTORY = 'directory';
    private const QUERY_PARAM_HTTP_METHOD = 'http-method';

    /** @var string[] */
    private $availableMethods = [
        'DELETE',
        'GET',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'POST',
        'PUT',
    ];
    /** @var Filesystem */
    private $filesystem;
    /** @var Response */
    private $response;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(Filesystem $filesystem, Response $response)
    {
        $this->filesystem = $filesystem;
        $this->response = $response;
    }

    final public function respondToRequest(Request $request) : Response
    {
        $path = $request->getUri()->getPath();

        // @FIXME: The path can also come from a 'Slug' header

        $method = $this->getRequestMethod($request);

        $contents = $request->getBody()->getContents();

        return $this->handle($method, $path, $contents);
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function getRequestMethod(Request $request) : string
    {
        $method = $request->getMethod();

        $queryParams = $request->getQueryParams();

        if (
            array_key_exists(self::QUERY_PARAM_HTTP_METHOD, $queryParams)
            && in_array(strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]), $this->availableMethods, true)
        ) {
            $method = strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]);
        }

        return $method;
    }

    private function handle(string $method, string $path, $contents) : Response
    {
        $response = $this->response;

        // Lets assume the worst...
        $response = $response->withStatus(500);

        // Set Accept, Allow, and CORS headers
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials','true')
            ->withHeader('Access-Control-Allow-Headers', 'Accept')
            // @FIXME: Add correct headers to resources (for instance allow DELETE on a GET resource)
            // ->withAddedHeader('Accept-Patch', 'text/ldpatch')
            // ->withAddedHeader('Accept-Post', 'text/turtle, application/ld+json, image/bmp, image/jpeg')
            // ->withHeader('Allow', 'GET, HEAD, OPTIONS, PATCH, POST, PUT');
        ;

        switch ($method) {
            case 'DELETE':
                $response = $this->handleDeleteRequest($response, $path, $contents);
                break;

            case 'GET':
            case 'HEAD':
                $response = $this->handleReadRequest($response, $path, $contents);
                if ($method === 'HEAD') {
                    $response->getBody()->rewind();
                    $response->getBody()->write('');
                }
                break;

            case 'OPTIONS':
                $response = $response
                    ->withHeader('Vary', 'Accept')
                    ->withStatus('204')
                ;
                break;

            case 'PATCH':
                $response->getBody()->write(self::ERROR_NOT_IMPLEMENTED_SPARQL);
                $response = $response->withStatus(501);
                break;

            case 'POST':
                // @TODO: Handle creation of a directory/container
                $response = $this->handleCreateRequest($response, $path, $contents);
                break;

            case 'PUT':
                // @TODO: Handle update of a directory/container
                $response = $this->handleUpdateRequest($response, $path, $contents);
                break;

            default:
                $message = vsprintf(self::ERROR_UNKNOWN_HTTP_METHOD, [$method]);
                throw new \LogicException($message);
                break;
        }

        return $response;
    }

    private function handleCreateRequest(Response $response, string $path, $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_POST_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            // @FIXME: Handle error scenarios correctly (for instance trying to create a file underneath another file)
            $success = $filesystem->write($path, $contents);
            $response = $response->withStatus($success ? 201 : 500);
        }

        return $response;
}

    private function handleDeleteRequest(Response $response, string $path, $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path)) {
            $mimetype = $filesystem->getMimetype($path);

            if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                $directoryContents = $filesystem->listContents($path, true);

                if (count($directoryContents) > 0) {
                    $status = 400;
                    $message = vsprintf(self::ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER, [$path]);
                    $response->getBody()->write($message);
                } else {
                    $success = $filesystem->deleteDir($path);

                    $status = $success ? 204 : 500;
                }
            } else {
                $success = $filesystem->delete($path);

                $status = $success ? 204 : 500;
            }

            $response = $response->withStatus($status);
        } else {
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        }

        return $response;
    }

    private function handleUpdateRequest(Response $response, string $path, string $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === false) {
            $message = vsprintf(self::ERROR_PUT_NON_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = $filesystem->update($path, $contents);
            $response = $response->withStatus($success ? 201 : 500);
        }

        return $response;
    }

    private function handleReadRequest(Response $response, string $path, $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === false) {
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        } else {
            $mimetype = $filesystem->getMimetype($path);

            if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                $listContents = $filesystem->listContents($path, true);
                $contents = json_encode($listContents);
                $response->getBody()->write($contents);

                $response = $response->withStatus(200);
            } else {
                $contents = $filesystem->read($path);

                if ($contents !== false) {
                    $response->getBody()->write($contents);
                    $response = $response->withStatus(200);
                }
            }
        }

        return $response;
    }
}
