<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SocietyController
{
    public function listMine(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $userId = (int) $authUser['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT s.id, s.name, s.description, sm.role AS member_role
             FROM society_members sm
             JOIN societies s ON s.id = sm.society_id
             WHERE sm.user_id = :user_id
             ORDER BY s.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $this->successResponse($response, $stmt->fetchAll(), null, 200);
    }

    private function successResponse(Response $response, mixed $data, ?string $message, int $status): Response
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
