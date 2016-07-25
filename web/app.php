<?php

use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\Authentication\BasicAuth;
use Http\Message\Authentication\Chain;
use Http\Message\Authentication\RequestConditional;
use Http\Message\RequestMatcher\RequestMatcher;
use JsonSchema\Validator;
use Slim\App;
use Slim\Container;
use App\Handler\ConstraintViolationHandler;
use App\Handler\ErrorHandlerLocator;
use App\Middleware\RequestValidatorMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware\HttpBasicAuthentication;
use Swagger\Client\Http\Client;
use Swagger\RequestValidator;
use Swagger\SchemaLoader;
use Symfony\Component\Config\ConfigCache;

require __DIR__ . '/../vendor/autoload.php';

$c = new Container([

    /***********
     * Settings
     ***********/

    'settings' => [
        'debug' => true,
        'auth' => [
            'secure' => false,
            'users' => [
                'foo' => 'bar'
            ]
        ]
    ],


    /*******************
     * Swagger Services
     *******************/

    'swagger.cache' => function($c) {
        return new ConfigCache(
            __DIR__ . '/../var/cache/schema.php',
            $c['settings']['debug']
        );
    },
    'swagger.schema_manager' => function($c) {
        return (new SchemaLoader(
            $c['swagger.cache']
        ))->load(__DIR__ . '/../schema.yml');
    },
    'swagger.request_validator' => function($c) {
        return new RequestValidator(
            $c['swagger.schema_manager'],
            new Validator()
        );
    },

    /*************
     * Middleware
     *************/

    'middleware.request_validator' => function($c) {
        return new RequestValidatorMiddleware(
            $c['swagger.request_validator']
        );
    },
    'middleware.http_basic' => function ($c) {
        return new HttpBasicAuthentication($c['settings']['auth']);
    },

    /****************
     * HTTP Services
     ****************/

    'http.factory.uri' => function () {
        return UriFactoryDiscovery::find();
    },
    'http.factory.client' => function () {
        return new \Http\Client\Common\PluginClient(
            HttpClientDiscovery::find(),
            [
                new AuthenticationPlugin(
                    new Chain(
                        [
                            new RequestConditional(
                                new RequestMatcher(null, 'localhost'),
                                new BasicAuth('foo', 'bar')
                            )
                        ]
                    )
                )
            ]
        );
    },
    'http.factory.message' => function () {
        return MessageFactoryDiscovery::find();
    },
    'http.uri_template' => function () {
        return new \Swagger\Client\Http\UriTemplate\Rize\UriTemplate(
          new \Rize\UriTemplate()
        );
    },
    'http.client' => function ($c) {
        return new Client(
            $c['http.factory.uri']->createUri('http://localhost'),
            $c['http.uri_template'],
            $c['http.factory.client'],
            $c['http.factory.message'],
            $c['swagger.schema_manager'],
            $c['swagger.request_validator']
        );
    },

    'errorHandler' => function() {
        return new ErrorHandlerLocator([
            new ConstraintViolationHandler()
        ]);
    }
]);

$app = new App($c);

$app->get('/', function ($req, $res) {
    $res->write('foo API demo');
});

/*********************
 * API implementation
 *********************/

/**
 * It show Request validation using Swagger specs
 *
 * @todo generate fake payload
 */
$app->group('/api', function() use ($c) {

    $this->get('/foos', function(Request $req, Response $res) {
       return $res->write('foo list placeholder');
    });

    $this->post('/foos', function(Request $req, Response $res) use($c) {
        return $res
            ->withStatus(201, 'Created')
            ->withHeader('Location', $req->getUri()->withPath('/api/foos/1'));
    });

    $this->put('/foos', function(Request $req, Response $res) {
        return $res->write("post foo placeholder");
    });

    $this->get('/foos/{id}', function(Request $req, Response $res, array $args) {
        return $res->write("get foo #${args['id']} placeholder");
    });

    $this->delete('/foos/{id}', function(Request $req, Response $res, array $args) {
        return $res->write("delete foo #${args['id']} placeholder");
    });

})->add($c['middleware.request_validator'])->add($c['middleware.http_basic']);

/**********************
 * Client implementation
 *********************/

$app->get(
    '/client',
    function(Request $req, Response $res) use ($c) {

        /** @var Client $client */
        $client = $c['http.client'];

        // Issue requests

        $create = $client->callAsync('CreateFoo',
            [
                'body' => [
                    'bar' => (new \DateTime)->format(\DateTime::RFC3339),
                    'baz' => 'http://domain.tld'
                ]
            ]
        );

        $list = $client->callAsync('GetFooList');

        $modify = $client->callAsync('ModifyFoo',
            [
                'body' => [
                    'id' => 1,
                    'bar' => (new \DateTime)->format(\DateTime::RFC3339),
                    'baz' => 'http://domain.tld'
                ]
            ]
        );

        $delete = $client->callAsync('DeleteFoo', ['id' => 1]);

        $promise = \GuzzleHttp\Promise\all([$create, $list, $modify, $delete]);

        var_dump($promise->wait());

        $res->write('nothing to see yet');
    }
);

$app->run();