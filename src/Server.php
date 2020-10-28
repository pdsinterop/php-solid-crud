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
    public const ERROR_PATH_EXISTS = 'Requested path "%s" already exists';
    public const ERROR_POST_EXISTING_RESOURCE = 'Requested path "%s" already exists. Can not "POST" to existing resource. Use "PUT" instead';
    public const ERROR_PUT_NON_EXISTING_RESOURCE = self::ERROR_PATH_DOES_NOT_EXIST . '. Can not "PUT" non-existing resource. Use "POST" instead';
    public const ERROR_PUT_EXISTING_RESOURCE = self::ERROR_PATH_EXISTS . '. Can not "PUT" existing container.';
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
		$link = $request->getHeaderLine("Link");
		// <http://www.w3.org/ns/ldp#BasicContainer>; rel="type"

        return $this->handle($method, $path, $contents, $link);
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

    private function handle(string $method, string $path, $contents, $link) : Response
    {
        $response = $this->response;
        $filesystem = $this->filesystem;

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
				if ($filesystem->has($path) === true) {
					$mimetype = $filesystem->getMimetype($path);
					if ($mimetype === self::MIME_TYPE_DIRECTORY) {
						$filename = $this->guid();
						$response = $this->handleCreateRequest($response, $path . $filename, $contents);
					} else {
						$response = $this->handleUpdateRequest($response, $path, $contents);
					}
				} else {
					$response = $this->handleCreateRequest($response, $path, $contents);
				}
			break;
            case 'PUT':
				switch ($link) {
					case '<http://www.w3.org/ns/ldp#BasicContainer>; rel="type"':
						$response = $this->handleCreateDirectoryRequest($response, $path);
					break;
					default:
						if ($filesystem->has($path) === true) {
							$response = $this->handleUpdateRequest($response, $path, $contents);
						} else {
							$response = $this->handleCreateRequest($response, $path, $contents);
						}
					break;
				}
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
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            // @FIXME: Handle error scenarios correctly (for instance trying to create a file underneath another file)
            $success = $filesystem->write($path, $contents);
			if ($success) {
				$response = $response->withHeader("Location", $path);
				$response = $response->withStatus(201);
			} else {
				$response = $response->withStatus(500);
			}
        }

        return $response;
	}

    private function handleCreateDirectoryRequest(Response $response, string $path) : Response
    {
        $filesystem = $this->filesystem;
        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
			$success = $filesystem->createDir($path);
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
				$success = $filesystem->deleteDir($path);

				$status = $success ? 204 : 500;
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
                $contents = $this->listDirectoryAsTurtle($path);
                $response->getBody()->write($contents);
				$response = $response->withHeader("Content-type", "text/turtle");
                $response = $response->withStatus(200);
            } else {
                $contents = $filesystem->read($path);
				$mimetype = $filesystem->getMimetype($path);

                if ($contents !== false) {
                    $response->getBody()->write($contents);
					$response = $response->withHeader("Content-type", $mimetype);
                    $response = $response->withStatus(200);
                }
            }
        }

        return $response;
    }
	
	private function guid() {
		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}

	private function listDirectoryAsTurtle($path) {
        $filesystem = $this->filesystem;
		$listContents = $filesystem->listContents($path);
		
		
		$name = basename($path) . ":";

		$turtle = array(
			"$name" => array(
				"a" => array("ldp:BasicContainer", "ldp:Container"),
				"ldp:contains" => array()
			)
		);
		
		foreach ($listContents as $item) {
			switch($item['type']) {
				case "file":
					$filename = "<" . $item['basename'] . ">";
					$turtle[$filename] = array(
						"a" => array("ldp:Resource")
					);
					$turtle[$name]['ldp:contains'][] = $filename;
				break;
				case "dir":
					$filename = "<" . $item['basename'] . ">";
					$turtle[$filename] = array(
						"a" => array("ldp:BasicContainer", "ldp:Container")
					);
					$turtle[$name]['ldp:contains'][] = $filename;
				break;
				default:
					throw new \Exception("Unknown type", 500);
				break;
			}
		}

		$container = <<< EOF
@prefix : <#>.
@prefix $name <>.
@prefix ldp: <http://www.w3.org/ns/ldp#>.

EOF;

		foreach ($turtle as $name => $item) {
			$container .= "\n$name\n";
			$lines = [];
			foreach ($item as $property => $values) {
				if (sizeof($values)) {
					$lines[] = "\t" . $property . " " . implode(", ", $values);
				}
			}
			
			$container .= implode(";\n", $lines);
			$container .= ".\n";
		}

		return $container;
	}
}
