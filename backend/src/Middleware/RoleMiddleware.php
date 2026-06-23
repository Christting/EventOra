<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

// Sits AFTER JwtMiddleware on a route. JwtMiddleware already verified the
// token and attached decoded user info to the request - this middleware
// just checks whether that user's role is in the allowed list.
//
// Usage: ->add(new JwtMiddleware())->add(new RoleMiddleware(['faculty_admin']))
// Middleware added later runs FIRST, so RoleMiddleware must be added
// after JwtMiddleware in the chain (Slim executes them in a stack,
// last-added runs first - see note in index.php where these are wired up).
class RoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');

        // If this fires, JwtMiddleware wasn't added before this one in the
        // route chain - a wiring mistake, not a client error, but we still
        // fail safely with 401 rather than crashing.
        if ($user === null) {
            return $this->forbiddenResponse('No authenticated user found on request');
        }

        if (!in_array($user['role'], $this->allowedRoles, true)) {
            return $this->forbiddenResponse(
                'You do not have permission to access this resource'
            );
        }

        return $handler->handle($request);
    }

    private function forbiddenResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => $message,
                'fields' => [],
            ],
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}