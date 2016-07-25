<?php
namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Body;
use Swagger\ConstraintViolation;
use Swagger\Exception\ConstraintViolations;

class ConstraintViolationHandler implements ErrorHandler
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \Exception|ConstraintViolations $exception
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response, \Exception $exception)
    {
        $data = array_map(
            function(ConstraintViolation $constraint) {
                return $constraint->toArray();
            },
            $exception->getViolations()
        );

        $body = new Body(fopen('php://temp', 'r+'));
        $body->write(json_encode(['errors' => $data]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    public function support(\Exception $exception)
    {
        $instance = ConstraintViolations::class;

        return ($exception instanceof $instance);
    }

}