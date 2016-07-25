<?php
namespace Swagger;

use JsonSchema\Validator;
use Psr\Http\Message\RequestInterface;
use Swagger\Exception\ConstraintViolations;

/**
 * Validate a Request against the API Specification
 */
class RequestValidator
{
    /**
     * @var SchemaManager
     */
    private $schemaManager;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var ConstraintViolation[]
     */
    private $violations;

    public function __construct(SchemaManager $schemaManager, Validator $validator)
    {
        $this->schemaManager = $schemaManager;
        $this->validator = $validator;
    }

    /**
     * Validate a PSR7 Request Message
     *
     * @param RequestInterface $request
     *
     * @throws ConstraintViolations
     */
    public function validateRequest(RequestInterface $request)
    {
        $requestPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        if ( ! $this->schemaManager->findPathInTemplates($requestPath, $path, $params) ) {
            throw new \InvalidArgumentException(sprintf('Unable to find "%s" in Swagger definition', $requestPath));
        }

        $this->validateMediaType($request->getHeaderLine('Content-Type'), $path, $method);
        $this->validateRequestHeaders($request->getHeaders(), $path, $method);
        $this->validateRequestQuery($request->getUri()->getQuery(), $path, $method);

        if (in_array($request->getMethod(), ['PUT', 'PATCH', 'POST'])) {
            $this->validateRequestBody((string) $request->getBody(), $path, $method);
        }

        if ($this->hasViolations()) {
            throw $this->getConstraintViolationsException();
        }
    }

    /**
     * Validate Request Body and return an array of errors
     *
     * @param string $requestBody A Request body string
     * @param string $path A Schema path as described in Swagger
     * @param string $method An HTTP Method
     *
     * @return array
     */
    public function validateRequestBody($requestBody, $path, $method)
    {
        $bodySchema = $this->schemaManager->getRequestSchema($path, $method);

        $this->validateAgainstSchema(
            json_decode($requestBody),
            $bodySchema,
            'body'
        );
    }

    /**
     * Validate an array of request headers
     *
     * @param array $headers
     * @param string $path
     * @param string $httpMethod
     */
    public function validateRequestHeaders(array $headers, $path, $httpMethod)
    {
        $schemaHeaders = $this->schemaManager->getRequestHeadersParameters($path, $httpMethod);

        $this->validateParameters(array_change_key_case($headers, CASE_LOWER), $schemaHeaders, 'header');
    }

    /**
     * Validate an array of Request query parameters
     *
     * @param string $queryString
     * @param string $path
     * @param string $method
     */
    public function validateRequestQuery($queryString, $path, $method)
    {
        parse_str($queryString, $queryParams);

        if (! empty($queryParams)) {
            $schemaQueryParams = $this->schemaManager->getQueryParameters($path, $method);
            $normalizedQueryParams = $this->getNormalizedQueryParams($queryParams, $schemaQueryParams);

            $this->validateParameters($normalizedQueryParams, $schemaQueryParams, 'query');
        }
    }


    /**
     * Validate the Request ContentType
     *
     * @param string $contentType
     * @param string $path
     * @param string $httpMethod
     */
    public function validateMediaType($contentType, $path, $httpMethod)
    {
        // Validate Request MediaType
        $allowedMediaTypes = $this->schemaManager->getRequestMediaTypes($path, $httpMethod);

        if (! in_array($contentType, $allowedMediaTypes)) {
            $this->addViolation(
                new ConstraintViolation(
                    'content-type',
                    sprintf(
                        '%s is not an allowed media type, supported: %s',
                        $contentType,
                        implode(',', $allowedMediaTypes)
                    ),
                    'required',
                    'header'
                )
            );
        }
    }

    private function validateParameters(array $params, array $schemaParams, $location)
    {
        $keys = [];
        foreach ($schemaParams as $schemaParam) {
            $keys[] = $schemaParam->name;
        }

        $required = [];
        foreach ($schemaParams as $schemaParam) {
            if (isset($schemaParam->required) && $schemaParam->required === true) {
                $required[] = $schemaParam->name;
            }
        }

        $validationSchema = new \stdClass();
        $validationSchema->type = 'object';
        $validationSchema->required = $required;
        $validationSchema->properties = (object) array_combine($keys, $schemaParams);

        $this->validateAgainstSchema(
            (object) $params,
            $validationSchema,
            $location
        );
    }

    /**
     * Normalize parameters
     *
     * @param array $params
     * @param array $schemaParams
     *
     * @todo Well, It may be useful for one user to retrieve nice and clean query parameters
     *
     * @return array An array of query parameters with to right type
     */
    public function getNormalizedQueryParams(array $params, array $schemaParams)
    {
        foreach ($schemaParams as $schemaParam) {
            $name = $schemaParam->name;
            $type = isset($schemaParam->type) ? $schemaParam->type : 'string';
            if (array_key_exists($name, $params)) {
                switch ($type) {
                    case 'boolean':
                        if ($params[$name] === 'false') {
                            $params[$name] = false;
                        }
                        if ($params[$name] === 'true') {
                            $params[$name] = true;
                        }
                        if (in_array($params[$name], ['0', '1'])) {
                            $params[$name] = (bool) $params[$name];
                        }
                        break;
                    case 'integer':
                        $params[$name] = (int) $params[$name];
                        break;
                    case 'number':
                        $params[$name] = (float) $params[$name];
                        break;
                }
            }
        }

        return $params;
    }

    /**
     * Validate a value against a JSON Schema
     *
     * @param mixed $value
     * @param object $schema
     * @param string $location
     */
    private function validateAgainstSchema($value, $schema, $location)
    {
        $this->validator->reset();
        $this->validator->check($value, $schema);

        $violations = array_map(
            function($error) use ($location) {
                return new ConstraintViolation(
                    $error['property'],
                    $error['message'],
                    $error['constraint'],
                    $location
                );
            },
            $this->validator->getErrors()
        );

        foreach ($violations as $violation) {
            $this->addViolation($violation);
        }
    }

    private function addViolation(ConstraintViolation $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * Indicate if we have violations in the pipe
     *
     * @return bool
     */
    public function hasViolations()
    {
        return (!empty($this->violations));
    }

    /**
     * @return ConstraintViolation[]
     */
    public function getViolations()
    {
        return $this->violations;
    }

    /**
     * Return an exception containing an array of ConstraintViolation
     *
     * @return ConstraintViolations
     */
    public function getConstraintViolationsException()
    {
        return new ConstraintViolations($this->violations);
    }

}