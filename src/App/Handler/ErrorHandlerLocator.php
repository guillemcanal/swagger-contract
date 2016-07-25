<?php
namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\Error;

class ErrorHandlerLocator
{
    /**
     * @var ErrorHandler[]
     */
    private $handlers;

    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->addErrorHandler($handler);
        }
    }

    public function addErrorHandler(ErrorHandler $handler)
    {
        $this->handlers[] = $handler;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, \Exception $exception)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->support($exception)) {
                return $handler->handle($request, $response, $exception);
            }
        }

        // delegate to the default error handler
        $defaultErrorHandler = new Error(true);

        return $defaultErrorHandler($request, $response, $exception);
    }
}