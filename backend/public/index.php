<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\DashboardController;
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

// CORS: allow the Vite dev server (localhost:5173) to call this API.
// Without this, the browser blocks every request before it even
// reaches our routes - this is purely a browser-side security check,
// not something PHP can see or control after the fact.
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

// Browsers send a separate OPTIONS "preflight" request before the real
// POST/PUT/etc request, just to check the CORS headers above. This
// route catches that preflight and returns 200 immediately, without
// running any of our actual route logic (auth, DB, etc.) for it.
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

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

// ============================================
// Admin Approval routes
// ============================================
// Every route in this group requires BOTH a valid JWT AND the
// faculty_admin role. The ->add() order below looks backwards on
// purpose - Slim's middleware stack runs LAST-ADDED-FIRST, so writing
// RoleMiddleware ->add() first and JwtMiddleware ->add() second means
// the actual execution order at request time is:
//   1. JwtMiddleware runs first - decodes the token, attaches `user`
//      to the request, or returns 401 if the token is missing/invalid.
//   2. RoleMiddleware runs second - checks the now-attached `user.role`
//      against ['faculty_admin'], or returns 403 if it doesn't match.
// This is the same verified pattern used and tested earlier when
// RoleMiddleware was first built.
$app->group('/api/admin/events', function ($group) {
    $controller = new AdminController();
 
    $group->get('/pending', [$controller, 'listPendingEvents']);
    $group->post('/{id}/approve', [$controller, 'approveEvent']);
    $group->post('/{id}/reject', [$controller, 'rejectEvent']);
})
    ->add(new RoleMiddleware(['faculty_admin']))
    ->add(new JwtMiddleware());

// ============================================
// Organiser Dashboard route
// ============================================
// Same middleware stacking pattern as the admin group above - just with
// a different allowed role. JwtMiddleware (outer, runs first) decodes
// the token; RoleMiddleware (inner, runs second) checks for 'organiser'.
$app->get('/api/dashboard/organiser', [new DashboardController(), 'organiserDashboard'])
    ->add(new RoleMiddleware(['organiser']))
    ->add(new JwtMiddleware());

$app->run();