<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\JwtHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;
use Firebase\JWT\ExpiredException;
use Exception;

// Sits in front of any route that requires a logged-in user.
// Reads the Authorization header, verifies the token, and if it's valid,
// attaches the decoded user info onto the request so controllers can read it.
class JwtMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or malformed Authorization header');
        }

        // Strip the "Bearer " prefix to get the raw token string
        $token = substr($authHeader, 7);

        try {
            $decoded = JwtHelper::decodeToken($token);
        } catch (ExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired, please log in again');
        } catch (Exception $e) {
            return $this->unauthorizedResponse('Invalid token');
        }

        // Make the decoded user data available to any controller further
        // down the pipeline via $request->getAttribute('user')
        $request = $request->withAttribute('user', $decoded);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => $message,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}