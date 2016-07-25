<?php
namespace App\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Swagger\RequestValidator;

class RequestValidatorMiddleware
{
    /**
     * @var RequestValidator
     */
    private $validator;

    public function __construct(RequestValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param $next
     *
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, $next)
    {
        $this->validator->validateRequest($request);
        return $next($request, $response);
    }
}