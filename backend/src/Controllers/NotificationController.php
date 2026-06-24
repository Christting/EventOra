<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationController
{
    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $userId = (int) $user['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, type, title, message, related_event_id, is_read, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $this->successResponse($response, $stmt->fetchAll(), null, 200);
    }

    public function unreadCount(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $userId = (int) $user['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS unread_count
             FROM notifications
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $this->successResponse($response, [
            'unread_count' => (int) $row['unread_count'],
        ], null, 200);
    }

    public function markAsRead(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $userId = (int) $user['sub'];
        $notificationId = (int) $args['id'];

        $db = Database::getConnection();
        $checkStmt = $db->prepare(
            'SELECT id
             FROM notifications
             WHERE id = :id AND user_id = :user_id'
        );
        $checkStmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        if (!$checkStmt->fetch()) {
            return $this->errorResponse($response, 'NOTIFICATION_NOT_FOUND', 'Notification not found', [], 404);
        }

        $stmt = $db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        return $this->successResponse($response, null, 'Notification marked as read', 200);
    }

    public function markAllAsRead(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $userId = (int) $user['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);

        return $this->successResponse($response, [
            'updated_count' => $stmt->rowCount(),
        ], 'All notifications marked as read', 200);
    }

    public function sendEventReminders(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!in_array($user['role'], ['faculty_admin', 'organiser'], true)) {
            return $this->errorResponse($response, 'FORBIDDEN', 'Only organisers or faculty admins can send event reminders', [], 403);
        }

        $data = $request->getParsedBody() ?? [];
        $hoursBefore = isset($data['hours_before']) ? (int) $data['hours_before'] : 24;

        if ($hoursBefore < 1 || $hoursBefore > 168) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'hours_before must be between 1 and 168', [
                'hours_before' => 'Use a value from 1 to 168 hours',
            ], 422);
        }

        $createdCount = NotificationService::createUpcomingEventReminders($hoursBefore);

        return $this->successResponse($response, [
            'created_count' => $createdCount,
            'hours_before' => $hoursBefore,
        ], 'Event reminders sent', 200);
    }
    private function successResponse(Response $response, mixed $data, ?string $message, int $status): Response
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function errorResponse(Response $response, string $code, string $message, array $fields, int $status): Response
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields,
            ],
        ];

        $response->getBody()->write(json_encode($payload));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
