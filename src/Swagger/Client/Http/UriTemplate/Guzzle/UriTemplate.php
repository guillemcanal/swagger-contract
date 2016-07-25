<?php
namespace Swagger\Client\Http\UriTemplate\Guzzle;

use GuzzleHttp\UriTemplate as GuzzleUriTemplate;
use Swagger\Client\Http\UriTemplate\UriTemplate as UriTemplateInterface;

class UriTemplate implements UriTemplateInterface
{
    private $uriTemplate;

    public function __construct(GuzzleUriTemplate $uriTemplate)
    {
        $this->uriTemplate = $uriTemplate;
    }

    public function expand($uri, array $params = [])
    {
        return $this->uriTemplate->expand($uri, $params);
    }

    public function extract($template, $uri, $strict = false)
    {
        throw new \RuntimeException('Extraction not supported by the Guzzle implementation of UriTemplate');
    }

}