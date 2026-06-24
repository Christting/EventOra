<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

class NotificationService
{
    public static function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $relatedEventId = null
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message, related_event_id)
             VALUES (:user_id, :type, :title, :message, :related_event_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_event_id' => $relatedEventId,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function createForRole(
        string $role,
        string $type,
        string $title,
        string $message,
        ?int $relatedEventId = null
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE role = :role');
        $stmt->execute(['role' => $role]);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $createdCount = 0;
        foreach ($userIds as $userId) {
            self::create((int) $userId, $type, $title, $message, $relatedEventId);
            $createdCount++;
        }

        return $createdCount;
    }
}
