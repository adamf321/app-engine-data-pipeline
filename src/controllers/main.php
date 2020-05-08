<?php

namespace Nolte\Metrics\DataPipeline;

/**
 * Adds all the main controllers to $app.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Home - return a simple message explaining that this is not a valid route!
 */
$app->get('/', function (Request $request, Response $response) {
    return $response->write('Nothing to see here!<br>See the <a href="">README</a> for more info about this app.');
})->setName('home');
