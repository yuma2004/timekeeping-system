<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$request = Request::fromGlobals();
$router = new Router();

$app = new Application($router);
$app->registerRoutes();

$router->fallback(static function (Request $request): void {
    Response::text('not found', 404);
});

$router->dispatch($request);
