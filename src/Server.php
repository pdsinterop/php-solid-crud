<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Resources;

use League\Flysystem\FilesystemInterface as Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use WebSocket\Client;

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
	public const ERROR_CAN_NOT_PARSE_FOR_PATCH = 'Could not parse the requested resource for patching';
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
	private $pubsub;
	private $baseUrl;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(Filesystem $filesystem, Response $response)
    {
        $this->filesystem = $filesystem;
        $this->response = $response;
		$this->pubsub = '';
		$this->baseUrl = '';
		$this->basePath = '';
    }

	final public function getFilesystem() {
		return $this->filesystem;
	}
	final public function getResponse() {
		return $this->response;
	}

    final public function respondToRequest(Request $request) : Response
    {
        $path = $request->getUri()->getPath();
		if ($this->basePath) {
			$path = str_replace($this->basePath, "", $path);
		}
	$path = rawurldecode($path);
	
		// @FIXME: The path can also come from a 'Slug' header

        $method = $this->getRequestMethod($request);

        $contents = $request->getBody()->getContents();
		
        return $this->handle($method, $path, $contents, $request);
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

	public function setPubSubUrl($url) {
		$this->pubsub = $url;
	}
	public function setBaseUrl($url) {
		$this->baseUrl = $url;

		$uri = $this->baseUrl;
		$serverRequest = new \Laminas\Diactoros\ServerRequest(array(),array(), $this->baseUrl);
		$this->basePath = $serverRequest->getUri()->getPath();
	}

    private function handle(string $method, string $path, $contents, $request) : Response
    {
        $response = $this->response;
        $filesystem = $this->filesystem;

        // Lets assume the worst...
        $response = $response->withStatus(500);

        // Set Accept, Allow, and CORS headers
        $response = $response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Credentials','true')
            // ->withHeader('Access-Control-Allow-Headers', '*, authorization, accept, content-type')
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
				$mime = $this->getRequestedMimeType($request->getHeaderLine("Accept"));
                $response = $this->handleReadRequest($response, $path, $contents, $mime);
                if ($method === 'HEAD') {
                    $response->getBody()->rewind();
                    $response->getBody()->write('');
					$response = $response->withStatus("204"); // CHECKME: nextcloud will remove the updates-via header - any objections to give the 'HEAD' request a 'no content' response type?
					if ($this->pubsub) {
						$response = $response->withHeader("updates-via", $this->pubsub);
					}
                }
                break;

            case 'OPTIONS':
                $response = $response
                    ->withHeader('Vary', 'Accept')
                    ->withStatus('204')
                ;
                break;

            case 'PATCH':
				$contentType= $request->getHeaderLine("Content-Type");
				switch($contentType) {
					case "application/sparql-update":
                    case "application/sparql-update-single-match":
						$response = $this->handleSparqlUpdate($response, $path, $contents);
					break;
					default:
						$response = $response->withStatus(400);
					break;
				}
			break;
            case 'POST':
				$pathExists = $filesystem->has($path);
				if ($pathExists) {
					$mimetype = $filesystem->getMimetype($path);
				}
				if ($path === "/") {
					$pathExists = true;
					$mimetype = self::MIME_TYPE_DIRECTORY;
				}
				if ($pathExists === true) {
					if ($mimetype === self::MIME_TYPE_DIRECTORY) {
						$contentType= explode(";", $request->getHeaderLine("Content-Type"))[0];
						$slug = $request->getHeaderLine("Slug");
						if ($slug) {
							$filename = $slug;
						} else {
							$filename = $this->guid();
						}						
						// FIXME: make this list complete for at least the things we'd expect (turtle, n3, jsonld, ntriples, rdf);
						// FIXME: if no content type was passed, we should reject the request according to the spec;
						
						switch ($contentType) {
							case "text/plain":
								$filename .= ".txt";
							break;
							case "text/turtle":
								$filename .= ".ttl";
							break;
							case "text/html":
								$filename .= ".html";
							break;
							case "application/json":
							case "application/ld+json":
								$filename .= ".json";
							break;
						}

						$response = $this->handleCreateRequest($response, $path . $filename, $contents);
					} else {
						$response = $this->handleUpdateRequest($response, $path, $contents);
					}
				} else {
					$response = $this->handleCreateRequest($response, $path, $contents);
				}
			break;
            case 'PUT':
				$link = $request->getHeaderLine("Link");
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

	private function handleSparqlUpdate(Response $response, string $path, $contents) : Response
	{
        $filesystem = $this->filesystem;
		$graph = new \EasyRdf_Graph();

        if ($filesystem->has($path) === false) {
			$data = '';
		} else {
			// read ttl data
			$data = $filesystem->read($path);
		}

		try {
			// Assuming this is in our native format, turtle
			$graph->parse($data, "turtle"); 
			// FIXME: Adding this base will allow us to parse <> entries; , $this->baseUrl . $this->basePath . $path), but that breaks the build.
			// FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format instead of 'turtle'?

			// parse query in contents
			// FIXME: allow multiline results
			// if (preg_match_all("/((INSERT|DELETE).*{(.*)})+/s", $contents, $matches, PREG_SET_ORDER)) {
			if (preg_match_all("/((INSERT|DELETE).*{(.*)})+/", $contents, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					$command = $match[2];
					$triples = $match[3];

					// apply changes to ttl data
					switch($command) {
						case "INSERT":
							// insert $triple(s) into $graph
							$graph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;

						break;
						case "DELETE":
							// delete $triples from $graph
							$deleteGraph = new \EasyRdf_Graph();
							$deleteGraph->parse($triples, "turtle"); // FIXME: The triples here are in sparql format, not in turtle;
							$resources = $deleteGraph->resources();
							foreach ($resources as $resource) {
								$properties = $resource->propertyUris();
								foreach ($properties as $property) {
									$values = $resource->all($property);
									if (!sizeof($values)) {
										$graph->delete($resource, $property);
									} else {
										foreach ($values as $value) {
											$count = $graph->delete($resource, $property, $value);
											if ($count == 0) {
												throw new \Exception("Could not delete a value", 500);
											}
										}
									}
								}
							}
						break;
						default:
							throw new \Exception("Unimplemented SPARQL", 500);
						break;
					}
				}
			}

			// Assuming this is in our native format, turtle
			$output = $graph->serialise("turtle"); // FIXME: Use enums from namespace Pdsinterop\Rdf\Enum\Format?
			// write ttl data

			if ($filesystem->has($path) === true) {
				$success = $filesystem->update($path, $output);
				$response = $response->withStatus($success ? 201 : 500);
			} else {
				$success = $filesystem->write($path, $output);
				$response = $response->withStatus($success ? 201 : 500);
			}
			if ($success) {
				$this->sendWebsocketUpdate($path);
			}
		} catch (\EasyRdf_Exception $exception) {
			$response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
			$response = $response->withStatus(501);
		} catch (\Exception $exception) {
			$response->getBody()->write(self::ERROR_CAN_NOT_PARSE_FOR_PATCH);
			$response = $response->withStatus(501);
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
				$response = $response->withHeader("Location", $this->baseUrl . $path);
				$response = $response->withStatus(201);
				$this->sendWebsocketUpdate($path);
			} else {
				$response = $response->withStatus(500);
			}
        }

        return $response;
	}
	private function parentPath($path) {
		if ($path == "/") {
			return "/";
		}
		$pathicles = explode("/", $path);
		$end = array_pop($pathicles);
		if ($end == "") {
			array_pop($pathicles);
		}
		return implode("/", $pathicles) . "/";
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
			if ($success) {
				$this->sendWebsocketUpdate($path);
			}
        }

        return $response;
	}

	private function sendWebsocketUpdate($path) {
		$pubsub = $this->pubsub;
		if (!$pubsub) {
			return; // no pubsub server available, don't even try;
		}
		
		$pubsub = str_replace("https://", "ws://", $pubsub);
		$pubsub = str_replace("http://", "ws://", $pubsub);

		$baseUrl = $this->baseUrl;

		$client = new \WebSocket\Client($pubsub, array(
			'headers' => array(
				'Sec-WebSocket-Protocol' => 'solid-0.1'
			)
		));
		$client->send("pub $baseUrl$path\n");

		while ($path != "/") {
			$path = $this->parentPath($path);
			$client->send("pub $baseUrl$path\n");
		}
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
					if ($success) {
						$this->sendWebsocketUpdate($path);
					}

                    $status = $success ? 204 : 500;
                }
            } else {
                $success = $filesystem->delete($path);
				if ($success) {
					$this->sendWebsocketUpdate($path);
				}
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
			if ($success) {
				$this->sendWebsocketUpdate($path);
			}
        }

        return $response;
    }

	private function getRequestedMimeType($accept) {		
		// text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8
		$mimes = explode(",", $accept);
		foreach ($mimes as $mime) {
                        $parts = explode(";", $mime);
                        $mimeInfo = $parts[0];
			switch ($mimeInfo) {
				case "text/turtle": // turtle
				case "application/ld+json": //json
				case "application/rdf+xml": //rdf
					return $mimeInfo;
				break;
			}
		}			
		return '';
	}
    private function handleReadRequest(Response $response, string $path, $contents, $mime='') : Response
    {
		$filesystem = $this->filesystem;
		if ($path == "/") { // FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
			$contents = $this->listDirectoryAsTurtle($path);
			$response->getBody()->write($contents);
			$response = $response->withHeader("Content-type", "text/turtle");
			$response = $response->withStatus(200);
		} else if ($filesystem->has($path) === false) {			
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
				if ($filesystem->asMime($mime)->has($path)) {
					$mimetype = $filesystem->asMime($mime)->getMimetype($path);
					$contents = $filesystem->asMime($mime)->read($path);
					if (preg_match('/.ttl$/', $path)) {
						$mimetype = "text/turtle"; // FIXME: teach  flysystem that .ttl means text/turtle
					} elseif (preg_match('/.acl$/', $path)) {
						$mimetype = "text/turtle"; // FIXME: teach flysystem that .acl files also want text/turtle
					} else {
						$mimetype = $filesystem->asMime($mime)->getMimetype($path);
					}
					if ($contents !== false) {
						$response->getBody()->write($contents);
						$response = $response->withHeader("Content-type", $mimetype);
						$response = $response->withStatus(200);
					}
				} else {
					$message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
					$response->getBody()->write($message);
					$response = $response->withStatus(404);
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
		if ($path == "/") {
			$listContents = $filesystem->listContents(".");// FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
		} else {
			$listContents = $filesystem->listContents($path);
		}
		// CHECKME: maybe structure this data als RDF/PHP
		// https://www.easyrdf.org/docs/rdf-formats-php
		
		$name = basename($path) . ":";
		// turtle syntax doesn't allow labels that start with a number, so prefix it if it does;
		if (preg_match("/^\d/", $name)) {
			$name = "container-" . $name;
		}
		$turtle = array(
			"<>" => array(
				"a" => array("ldp:BasicContainer", "ldp:Container", "ldp:Resource"),
				"ldp:contains" => array()
			)
		);
		
		foreach ($listContents as $item) {
			switch($item['type']) {
				case "file":
					$filename = "<" . rawurlencode($item['basename']) . ">";
					$turtle[$filename] = array(
						"a" => array("ldp:Resource")
					);
					$turtle["<>"]['ldp:contains'][] = $filename;
				break;
				case "dir":
					// FIXME: we have a trailing slash here to please the test suits, but it probably should also pass without it since we are a Container.
					$filename = "<" . rawurlencode($item['basename']) . "/>"; 
					$turtle[$filename] = array(
						"a" => array("ldp:BasicContainer", "ldp:Container", "ldp:Resource")
					);
					$turtle["<>"]['ldp:contains'][] = $filename;
				break;
				default:
					throw new \Exception("Unknown type", 500);
				break;
			}
		}

		$container = <<< EOF
@prefix : <#>.
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
