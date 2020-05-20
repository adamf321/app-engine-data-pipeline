<?php

namespace Nolte\Metrics\DataPipeline;

/**
 * Adds all the main controllers to $app.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Dzava\Lighthouse\Lighthouse;

/**
 * Home - return a simple message explaining that this is not a valid route!
 */
$app->get('/lighthouse', function (Request $request, Response $response) {
    

    exec("cd /var/www/html/projectfolder/js; node nodefunc.js 2>&1", $out, $err);
})->setName('lighthouse');
