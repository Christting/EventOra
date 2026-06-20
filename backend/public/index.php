<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Controllers\AuthController;
use App\Middleware\JwtMiddleware;
use App\Middleware\RoleMiddleware;

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

// ============================================
// Auth routes 
// ============================================
$app->group('/api/auth', function ($group) {
    $controller = new AuthController();

    // Public - no JwtMiddleware needed
    $group->post('/register', [$controller, 'register']);
    $group->post('/login', [$controller, 'login']);

    // Authenticated - JwtMiddleware runs first, decodes the token,
    // and attaches user info to the request before these run
    $group->post('/refresh', [$controller, 'refresh'])->add(new JwtMiddleware());
    $group->post('/logout', [$controller, 'logout'])->add(new JwtMiddleware());
});

// GET/PUT /api/me - also part of the auth contract but not under /api/auth
// since it's a profile resource, not an auth action
$app->group('/api/me', function ($group) {
    $controller = new AuthController();

    $group->get('', [$controller, 'me']);
    $group->put('', [$controller, 'updateMe']);
})->add(new JwtMiddleware());

$app->run();