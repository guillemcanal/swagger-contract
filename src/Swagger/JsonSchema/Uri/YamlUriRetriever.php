<?php
namespace Swagger\JsonSchema\Uri;

use JsonSchema\Uri\UriRetriever;
use Symfony\Component\Yaml\Yaml;

/**
 * Load Schema From a YAML file
 *
 * @todo Meh...
 */
class YamlUriRetriever extends UriRetriever
{
    /**
     * @var array|object[]
     * @see loadSchema
     */
    private $schemaCache = array();

    protected function loadSchema($fetchUri)
    {
        if (isset($this->schemaCache[$fetchUri])) {
            return $this->schemaCache[$fetchUri];
        }

        $contents = $this->getUriRetriever()->retrieve($fetchUri);

        $contents = Yaml::parse($contents);
        if (isset($contents['info']['description'])) {
            unset($contents['info']['description']);
        }

        $jsonSchema = json_decode(json_encode($contents));

        $this->schemaCache[$fetchUri] = $jsonSchema;

        return $jsonSchema;
    }

}