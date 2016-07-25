<?php
namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ErrorHandler
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \Exception $exception
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, ResponseInterface $response, \Exception $exception);

    /**
     * Indicate if the error handler support the Exception
     *
     * @param \Exception $exception
     *
     * @return bool
     */
    public function support(\Exception $exception);
}