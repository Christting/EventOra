<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env (DB credentials, JWT secret, etc.)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();

// If we deploy under a subpath later (e.g. /api), set it here.
// Leave empty for local dev.
// $app->setBasePath('/api');

// Error middleware: displayErrorDetails is true for now so we can see
// actual PHP errors while developing. MUST be set to false before
// deploying for the demo, or it'll leak internal error details to the client.
$app->addErrorMiddleware(true, true, true);

// Needed so $request->getParsedBody() actually works for JSON POST/PUT bodies
$app->addBodyParsingMiddleware();

// Quick health check route, just to confirm the backend is alive
$app->get('/', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'message' => 'EventOra backend is running',
        'status' => 'ok',
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();