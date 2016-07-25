<?php
namespace Swagger\Client\Http;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Promise\Promise;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Swagger\Client\Http\UriTemplate\UriTemplate;
use Swagger\RequestValidator;
use Swagger\SchemaManager;

/**
 * A client that provide API service commands (pretty much like Guzzle)
 */
class Client
{
    /**
     * @var SchemaManager
     */
    private $schemaManager;

    /**
     * @var RequestValidator
     */
    private $validator;

    /**
     * @var HttpAsyncClient|HttpClient
     */
    private $client;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var UriTemplate
     */
    private $uriTemplate;

    /**
     * @var UriInterface
     */
    private $baseUri;

    /**
     * @param UriInterface $baseUri The BaseUri of your API
     * @param UriTemplate $uriTemplate Used to expand Uri pattern in the API definition
     * @param HttpClient $client An HTTP client
     * @param MessageFactory $messageFactory
     * @param SchemaManager $schemaManager
     * @param RequestValidator $validator
     */
    public function __construct(
        UriInterface $baseUri,
        UriTemplate $uriTemplate,
        HttpClient $client,
        MessageFactory $messageFactory,
        SchemaManager $schemaManager,
        RequestValidator $validator
    ) {
        $this->baseUri = $baseUri;
        $this->uriTemplate = $uriTemplate;
        $this->schemaManager = $schemaManager;
        $this->validator = $validator;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
    }

    /**
     * @param $name
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function call($name, array $params = [])
    {
        return $this->client->sendRequest($this->getRequestFor($name, $params));
    }

    /**
     * @param string $name
     * @param array $params
     *
     * @return Promise
     */
    public function callAsync($name, array $params = [])
    {
        if (! $this->client instanceof HttpAsyncClient) {
            throw new \RuntimeException(
                sprintf(
                    '"%s" does not support async request',
                    get_class($this->client)
                )
            );
        }

        $request = $this->getRequestFor($name, $params);

        return $this->client->sendAsyncRequest($request);
    }

    /**
     * Create an PSR-7 Request from the API Specification
     *
     * @param string $name The name of the desired operation
     * @param array $params An array of parameters
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    private function getRequestFor($name, array $params)
    {
        $query = [];
        $headers = [];
        $uriParams = [];
        $body = null;

        $definition = $this->schemaManager->findDefinitionByOperationId($name);

        foreach ($definition->parameters as $parameter) {

            $name = $parameter->name;
            if (array_key_exists($name, $params)) {
                switch ($parameter->in) {
                    case 'query':
                        $query[$name] = $params[$name];
                        break;
                    case 'path';
                        $uriParams[$name] = $params[$name];
                        break;
                    case 'header';
                        $headers[$name] = $params[$name];
                        break;
                    case 'body':
                        $body = json_encode($params['body']);
                        break;
                }
            }
        }

        // @todo Find a may to guess the desired media type :\
        $headers['Content-Type'] = 'application/json';

        $request = $this->messageFactory->createRequest(
            $definition->method,
            $this->baseUri
                ->withPath($this->uriTemplate->expand($definition->pattern, $uriParams))
                ->withQuery(http_build_query($query))
            ,
            $headers,
            $body
        );

        $this->validator->validateRequest($request);

        return $request;
    }
}