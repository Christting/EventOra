<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\NotificationService;
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
        return $this->createEventFromRequest(
            $request,
            $response,
            'pending_approval',
            true,
            'Event created and submitted for approval'
        );
    }

    // POST /api/events/draft
    // Saves an event as draft without notifying faculty admins yet.
    public function createDraft(Request $request, Response $response): Response
    {
        return $this->createEventFromRequest(
            $request,
            $response,
            'draft',
            false,
            'Draft event saved'
        );
    }

    private function createEventFromRequest(
        Request $request,
        Response $response,
        string $status,
        bool $notifyFacultyAdmins,
        string $successMessage
    ): Response
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

        $errors = array_merge($errors, $this->validateEventSchedule($startDatetime, $endDatetime, $regDeadline));
        $errors = array_merge($errors, $this->validateEventFee($feeType, $feeAmount));

        if ($feeType === 'free') {
            $feeAmount = 0.00;
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
                'status' => $status,
            ]);

            $eventId = (int) $db->lastInsertId();

            if ($notifyFacultyAdmins) {
                $this->notifyFacultyAdminsOfPendingEvent($title, $eventId);
            }
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'DB_ERROR', 'Could not create event', [], 500);
        }

        return $this->successResponse($response, [
            'id' => $eventId,
            'title' => $title,
            'status' => $status,
        ], $successMessage, 201);
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
                e.cancellation_reason,
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

    public function preview(Request $request, Response $response, array $args): Response
    {
        $event = $this->findOwnedEvent($request, (int) $args['id']);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }

        $preview = $this->formatEventForFrontend($event);
        $preview['previewMode'] = true;
        $preview['canSubmitForApproval'] = in_array($event['status'], ['draft', 'rejected'], true);
        $preview['canEdit'] = in_array($event['status'], $this->editableStatuses, true);

        return $this->successResponse($response, $preview, null, 200);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->saveEventDetails(
            $request,
            $response,
            (int) $args['id'],
            'pending_approval',
            true,
            'Event updated and submitted for approval'
        );
    }

    public function saveDraft(Request $request, Response $response, array $args): Response
    {
        return $this->saveEventDetails(
            $request,
            $response,
            (int) $args['id'],
            'draft',
            false,
            'Draft saved'
        );
    }

    private function saveEventDetails(
        Request $request,
        Response $response,
        int $eventId,
        string $targetStatus,
        bool $notifyFacultyAdmins,
        string $successMessage
    ): Response
    {
        $event = $this->findOwnedEvent($request, $eventId);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], $this->editableStatuses, true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', 'This event cannot be edited in its current status', [], 400);
        }

        $data = $request->getParsedBody();
        $title = trim($data['title'] ?? $event['title']);
        $description = trim($data['description'] ?? ($event['description'] ?? ''));
        $venue = trim($data['venue'] ?? $event['venue']);
        $category = $data['category'] ?? $event['category'];
        $startDatetime = $data['start_datetime'] ?? $event['start_datetime'];
        $endDatetime = $data['end_datetime'] ?? $event['end_datetime'];
        $regDeadline = $data['reg_deadline'] ?? $event['reg_deadline'];
        $capacity = isset($data['capacity']) ? (int) $data['capacity'] : (int) $event['capacity'];
        $feeType = $data['fee_type'] ?? $event['fee_type'];
        $feeAmount = isset($data['fee_amount']) ? (float) $data['fee_amount'] : (float) $event['fee_amount'];
        $waitlistEnabled = isset($data['waitlist_enabled']) ? (int) (bool) $data['waitlist_enabled'] : (int) $event['waitlist_enabled'];
        $contactPerson = trim($data['contact_person'] ?? ($event['contact_person'] ?? '')) ?: null;
        $contactEmail = trim($data['contact_email'] ?? ($event['contact_email'] ?? '')) ?: null;
        $specialInstructions = trim($data['special_instructions'] ?? ($event['special_instructions'] ?? '')) ?: null;

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required';
        }
        if ($venue === '') {
            $errors['venue'] = 'Venue is required';
        }
        if (!in_array($category, $this->allowedCategories, true)) {
            $errors['category'] = 'Category must be one of: ' . implode(', ', $this->allowedCategories);
        }
        if ($capacity < 1) {
            $errors['capacity'] = 'Capacity must be a positive number';
        }
        if (!in_array($feeType, $this->allowedFeeTypes, true)) {
            $errors['fee_type'] = 'fee_type must be either free or paid';
        }

        $errors = array_merge($errors, $this->validateEventSchedule($startDatetime, $endDatetime, $regDeadline));
        $errors = array_merge($errors, $this->validateEventFee($feeType, $feeAmount));

        if ($feeType === 'free') {
            $feeAmount = 0.00;
        }

        if (!empty($errors)) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Validation failed', $errors, 422);
        }

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
            'status' => $targetStatus,
        ]);

        if ($notifyFacultyAdmins) {
            $this->notifyFacultyAdminsOfPendingEvent($title, $eventId);
        }

        return $this->successResponse($response, ['id' => $eventId, 'status' => $targetStatus], $successMessage, 200);
    }

    public function submitForApproval(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $event = $this->findOwnedEvent($request, $eventId);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], ['draft', 'rejected'], true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', "Event with status '{$event['status']}' cannot be submitted for approval", [], 400);
        }

        $errors = array_merge(
            $this->validateEventSchedule($event['start_datetime'], $event['end_datetime'], $event['reg_deadline']),
            $this->validateEventFee($event['fee_type'], (float) $event['fee_amount'])
        );

        if (!empty($errors)) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Validation failed', $errors, 422);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE events SET status = :status WHERE id = :id');
        $stmt->execute(['status' => 'pending_approval', 'id' => $eventId]);

        $this->notifyFacultyAdminsOfPendingEvent($event['title'], $eventId);

        return $this->successResponse($response, ['id' => $eventId, 'status' => 'pending_approval'], 'Event submitted for approval', 200);
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
        $data = $request->getParsedBody() ?? [];
        $cancellationReason = trim($data['reason'] ?? $data['cancellation_reason'] ?? '');

        if (strlen($cancellationReason) > 500) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Validation failed', [
                'reason' => 'Cancellation reason cannot be longer than 500 characters',
            ], 422);
        }

        return $this->changeStatus(
            $request,
            $response,
            (int) $args['id'],
            ['published'],
            'cancelled',
            'Event cancelled',
            true,
            false,
            $cancellationReason !== '' ? $cancellationReason : null
        );
    }

    public function complete(Request $request, Response $response, array $args): Response
    {
        return $this->changeStatus(
            $request,
            $response,
            (int) $args['id'],
            ['published'],
            'completed',
            'Event marked as completed'
        );
    }

    private function changeStatus(
        Request $request,
        Response $response,
        int $eventId,
        array $fromStatuses,
        string $toStatus,
        string $message,
        bool $notifyOrganiser = false,
        bool $notifyFacultyAdmins = false,
        ?string $cancellationReason = null
    ): Response
    {
        $event = $this->findOwnedEvent($request, $eventId);
        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }
        if (!in_array($event['status'], $fromStatuses, true)) {
            return $this->errorResponse($response, 'INVALID_STATE_TRANSITION', "Event with status '{$event['status']}' cannot perform this action", [], 400);
        }

        $db = Database::getConnection();
        if ($toStatus === 'cancelled') {
            $stmt = $db->prepare('UPDATE events SET status = :status, cancellation_reason = :cancellation_reason WHERE id = :id');
            $stmt->execute([
                'status' => $toStatus,
                'cancellation_reason' => $cancellationReason,
                'id' => $eventId,
            ]);
        } else {
            $stmt = $db->prepare('UPDATE events SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $toStatus, 'id' => $eventId]);
        }

        if ($notifyOrganiser) {
            $reasonText = $cancellationReason !== null ? " Reason: {$cancellationReason}" : '';

            NotificationService::create(
                (int) $event['created_by'],
                'event_cancelled',
                'Event cancelled',
                "Your event '{$event['title']}' has been cancelled.{$reasonText}",
                $eventId
            );

            NotificationService::createForEventRegistrants(
                $eventId,
                'event_cancelled',
                'Event cancelled',
                "The event '{$event['title']}' has been cancelled by the organiser.{$reasonText}",
                (int) $event['created_by']
            );
        }

        if ($notifyFacultyAdmins) {
            $this->notifyFacultyAdminsOfPendingEvent($event['title'], $eventId);
        }

        return $this->successResponse($response, ['id' => $eventId, 'status' => $toStatus], $message, 200);
    }

    private function validateEventSchedule(string $startDatetime, string $endDatetime, string $regDeadline): array
    {
        if ($startDatetime === '' || $endDatetime === '' || $regDeadline === '') {
            return [];
        }

        $startTimestamp = strtotime($startDatetime);
        $endTimestamp = strtotime($endDatetime);
        $deadlineTimestamp = strtotime($regDeadline);

        if ($startTimestamp === false || $endTimestamp === false || $deadlineTimestamp === false) {
            return [
                'datetime' => 'start_datetime, end_datetime, and reg_deadline must be valid datetime values',
            ];
        }

        $errors = [];
        if ($startTimestamp >= $endTimestamp) {
            $errors['end_datetime'] = 'end_datetime must be after start_datetime';
        }
        if ($deadlineTimestamp >= $startTimestamp) {
            $errors['reg_deadline'] = 'reg_deadline must be before start_datetime';
        }

        return $errors;
    }

    private function validateEventFee(string $feeType, float $feeAmount): array
    {
        if (!in_array($feeType, $this->allowedFeeTypes, true)) {
            return [];
        }

        if ($feeAmount < 0) {
            return ['fee_amount' => 'fee_amount cannot be negative'];
        }

        if ($feeType === 'paid' && $feeAmount <= 0) {
            return ['fee_amount' => 'Paid events must have a fee_amount greater than 0'];
        }

        return [];
    }

    private function notifyFacultyAdminsOfPendingEvent(string $eventTitle, int $eventId): void
    {
        NotificationService::createForRole(
            'faculty_admin',
            'event_pending_approval',
            'New event pending approval',
            "Event '{$eventTitle}' has been submitted and is waiting for Faculty Admin review.",
            $eventId
        );
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
            'cancellationReason' => $event['cancellation_reason'] ?? null,
            'cancellation_reason' => $event['cancellation_reason'] ?? null,
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
