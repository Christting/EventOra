<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDOException;

// Organiser event workflow endpoints. This keeps the create/edit/detail
// flow connected to the same events table used by Faculty Admin approval.
class EventController
{
    private array $allowedCategories = ['academic', 'sports', 'cultural', 'religious'];
    private array $allowedFeeTypes = ['free', 'paid'];
    private array $editableStatuses = ['draft', 'rejected', 'pending_approval'];

    // POST /api/events
    // Organiser-only. Creates an event directly in pending_approval status.
    // created_by is taken from the JWT, never from the request body.
    public function create(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $createdBy = (int) $authUser['sub'];

        $data = $request->getParsedBody();

        $societyId = isset($data['society_id']) ? (int) $data['society_id'] : null;
        $title = trim($data['title'] ?? '');
        $venue = trim($data['venue'] ?? '');
        $category = $data['category'] ?? '';
        $startDatetime = $data['start_datetime'] ?? '';
        $endDatetime = $data['end_datetime'] ?? '';
        $regDeadline = $data['reg_deadline'] ?? '';
        $description = trim($data['description'] ?? '');
        $capacity = isset($data['capacity']) ? (int) $data['capacity'] : null;
        $feeType = $data['fee_type'] ?? '';
        $feeAmount = isset($data['fee_amount']) ? (float) $data['fee_amount'] : 0.00;
        $waitlistEnabled = isset($data['waitlist_enabled']) ? (int) (bool) $data['waitlist_enabled'] : 1;
        $contactPerson = trim($data['contact_person'] ?? '') ?: null;
        $contactEmail = trim($data['contact_email'] ?? '') ?: null;
        $specialInstructions = trim($data['special_instructions'] ?? '') ?: null;

        $errors = [];

        if ($societyId === null) {
            $errors['society_id'] = 'society_id is required';
        }
        if ($title === '') {
            $errors['title'] = 'Title is required';
        }
        if ($venue === '') {
            $errors['venue'] = 'Venue is required';
        }
        if (!in_array($category, $this->allowedCategories, true)) {
            $errors['category'] = 'Category must be one of: ' . implode(', ', $this->allowedCategories);
        }
        if ($startDatetime === '' || $endDatetime === '' || $regDeadline === '') {
            $errors['datetime'] = 'start_datetime, end_datetime, and reg_deadline are all required';
        }
        if ($capacity === null || $capacity < 1) {
            $errors['capacity'] = 'Capacity must be a positive number';
        }
        if (!in_array($feeType, $this->allowedFeeTypes, true)) {
            $errors['fee_type'] = 'fee_type must be either free or paid';
        }

        if (!empty($errors)) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Validation failed', $errors, 422);
        }

        $db = Database::getConnection();

        if (!$this->organiserBelongsToSociety($db, $createdBy, $societyId)) {
            return $this->errorResponse($response, 'SOCIETY_NOT_FOUND', 'The specified society does not exist for this organiser', [], 404);
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO events (society_id, created_by, title, description, venue, category,
                    start_datetime, end_datetime, reg_deadline, capacity, fee_type, fee_amount,
                    waitlist_enabled, contact_person, contact_email, special_instructions, status)
                 VALUES (:society_id, :created_by, :title, :description, :venue, :category,
                    :start_datetime, :end_datetime, :reg_deadline, :capacity, :fee_type,
                    :fee_amount, :waitlist_enabled, :contact_person, :contact_email, :special_instructions, :status)'
            );
            $stmt->execute([
                'society_id' => $societyId,
                'created_by' => $createdBy,
                'title' => $title,
                'description' => $description,
                'venue' => $venue,
                'category' => $category,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'reg_deadline' => $regDeadline,
                'capacity' => $capacity,
                'fee_type' => $feeType,
                'fee_amount' => $feeAmount,
                'waitlist_enabled' => $waitlistEnabled,
                'contact_person' => $contactPerson,
                'contact_email' => $contactEmail,
                'special_instructions' => $specialInstructions,
                'status' => 'pending_approval',
            ]);

            $eventId = (int) $db->lastInsertId();
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'DB_ERROR', 'Could not create event', [], 500);
        }

        return $this->successResponse($response, [
            'id' => $eventId,
            'title' => $title,
            'status' => 'pending_approval',
        ], 'Event created and submitted for approval', 201);
    }

    // GET /api/events/mine
    // Organiser-only. Lists events created by the currently logged-in
    // organiser, regardless of status - useful for the organiser to see
    // what they've submitted and its current approval state.
    public function listMine(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $createdBy = (int) $authUser['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.id, e.title, e.description, e.venue, e.category, e.start_datetime, e.end_datetime,
                e.reg_deadline, e.capacity, e.fee_type, e.fee_amount, e.waitlist_enabled, e.status,
                e.created_at, e.updated_at, s.name AS society_name,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status <> "cancelled") AS registrations
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.created_by = :created_by
             ORDER BY e.created_at DESC'
        );
        $stmt->execute(['created_by' => $createdBy]);

        return $this->successResponse($response, array_map([$this, 'formatEventForFrontend'], $stmt->fetchAll()), null, 200);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $event = $this->findOwnedEvent($request, (int) $args['id']);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }

        return $this->successResponse($response, $this->formatEventForFrontend($event), null, 200);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $event = $this->findOwnedEvent($request, $eventId);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], $this->editableStatuses, true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', 'This event cannot be edited in its current status', [], 400);
        }

        $data = $request->getParsedBody();
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE events SET title = :title, description = :description, venue = :venue, category = :category,
                start_datetime = :start_datetime, end_datetime = :end_datetime, reg_deadline = :reg_deadline,
                capacity = :capacity, fee_type = :fee_type, fee_amount = :fee_amount,
                waitlist_enabled = :waitlist_enabled, contact_person = :contact_person,
                contact_email = :contact_email, special_instructions = :special_instructions,
                status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $eventId,
            'title' => trim($data['title'] ?? $event['title']),
            'description' => trim($data['description'] ?? $event['description']),
            'venue' => trim($data['venue'] ?? $event['venue']),
            'category' => $data['category'] ?? $event['category'],
            'start_datetime' => $data['start_datetime'] ?? $event['start_datetime'],
            'end_datetime' => $data['end_datetime'] ?? $event['end_datetime'],
            'reg_deadline' => $data['reg_deadline'] ?? $event['reg_deadline'],
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : (int) $event['capacity'],
            'fee_type' => $data['fee_type'] ?? $event['fee_type'],
            'fee_amount' => isset($data['fee_amount']) ? (float) $data['fee_amount'] : (float) $event['fee_amount'],
            'waitlist_enabled' => isset($data['waitlist_enabled']) ? (int) (bool) $data['waitlist_enabled'] : (int) $event['waitlist_enabled'],
            'contact_person' => trim($data['contact_person'] ?? $event['contact_person']) ?: null,
            'contact_email' => trim($data['contact_email'] ?? $event['contact_email']) ?: null,
            'special_instructions' => trim($data['special_instructions'] ?? $event['special_instructions']) ?: null,
            'status' => 'pending_approval',
        ]);

        return $this->successResponse($response, ['id' => $eventId, 'status' => 'pending_approval'], 'Event updated and submitted for approval', 200);
    }

    public function submitForApproval(Request $request, Response $response, array $args): Response
    {
        return $this->changeStatus($request, $response, (int) $args['id'], ['draft', 'rejected'], 'pending_approval', 'Event submitted for approval');
    }

    public function deleteDraft(Request $request, Response $response, array $args): Response
    {
        $event = $this->findOwnedEvent($request, (int) $args['id']);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], ['draft', 'rejected'], true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', 'Only draft or rejected events can be deleted', [], 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => (int) $args['id']]);

        return $this->successResponse($response, null, 'Event deleted', 200);
    }

    public function cancelSubmission(Request $request, Response $response, array $args): Response
    {
        return $this->changeStatus($request, $response, (int) $args['id'], ['pending_approval'], 'draft', 'Submission cancelled');
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        return $this->changeStatus($request, $response, (int) $args['id'], ['published'], 'cancelled', 'Event cancelled');
    }

    private function changeStatus(Request $request, Response $response, int $eventId, array $fromStatuses, string $toStatus, string $message): Response
    {
        $event = $this->findOwnedEvent($request, $eventId);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], $fromStatuses, true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', "Event with status '{$event['status']}' cannot perform this action", [], 400);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE events SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $toStatus, 'id' => $eventId]);

        return $this->successResponse($response, ['id' => $eventId, 'status' => $toStatus], $message, 200);
    }

    private function organiserBelongsToSociety(\PDO $db, int $organiserId, int $societyId): bool
    {
        $stmt = $db->prepare('SELECT id FROM society_members WHERE user_id = :user_id AND society_id = :society_id');
        $stmt->execute(['user_id' => $organiserId, 'society_id' => $societyId]);

        return (bool) $stmt->fetch();
    }

    private function findOwnedEvent(Request $request, int $eventId): ?array
    {
        $authUser = $request->getAttribute('user');
        $createdBy = (int) $authUser['sub'];

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.*, s.name AS society_name,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status <> "cancelled") AS registrations
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.id = :id AND e.created_by = :created_by'
        );
        $stmt->execute(['id' => $eventId, 'created_by' => $createdBy]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    private function formatEventForFrontend(array $event): array
    {
        return [
            'id' => (int) $event['id'],
            'title' => $event['title'],
            'description' => $event['description'] ?? '',
            'category' => $event['category'],
            'society' => $event['society_name'] ?? null,
            'society_name' => $event['society_name'] ?? null,
            'location' => $event['venue'],
            'venue' => $event['venue'],
            'startAt' => $event['start_datetime'],
            'endAt' => $event['end_datetime'],
            'registrationDeadline' => $event['reg_deadline'],
            'capacity' => (int) $event['capacity'],
            'feeType' => $event['fee_type'],
            'feeAmount' => (float) $event['fee_amount'],
            'waitlistEnabled' => (bool) $event['waitlist_enabled'],
            'status' => $event['status'],
            'registrations' => (int) ($event['registrations'] ?? 0),
            'createdAt' => $event['created_at'] ?? null,
            'updatedAt' => $event['updated_at'] ?? null,
        ];
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

    private function errorResponse(Response $response, string $code, string $message, array $fields, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'fields' => $fields],
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
