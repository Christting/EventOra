<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDOException;

// MINIMAL SCAFFOLD - NOT the real Event CRUD owned by Christ (Backend &
// API Lead - Organiser Core, per PR1 Team Role Split). This only exists
// so the organiser-side flow has a way to produce real pending_approval
// events for the Admin Approval Queue to actually review.
//
// Deliberately missing: draft status, the submit-for-approval step,
// poster upload, edit/cancel, and several optional PR1 fields
// (description, contact_person, contact_email, special_instructions).
// Christ's real implementation should replace or absorb this.
class EventController
{
    private array $allowedCategories = ['academic', 'sports', 'cultural', 'religious'];
    private array $allowedFeeTypes = ['free', 'paid'];

    // POST /api/events
    // Organiser-only. Creates an event directly in pending_approval status
    // (skips draft, since there's no separate "submit" step in this
    // scaffold). created_by is taken from the JWT, never from the
    // request body, so an organiser can't create events under another
    // user's name.
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
        $capacity = isset($data['capacity']) ? (int) $data['capacity'] : null;
        $feeType = $data['fee_type'] ?? '';
        $feeAmount = isset($data['fee_amount']) ? (float) $data['fee_amount'] : 0.00;

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

        // Confirm the society actually exists before inserting - cheaper
        // and clearer than letting the foreign key constraint fail with
        // a generic DB error.
        $checkStmt = $db->prepare('SELECT id FROM societies WHERE id = :id');
        $checkStmt->execute(['id' => $societyId]);
        if (!$checkStmt->fetch()) {
            return $this->errorResponse($response, 'SOCIETY_NOT_FOUND', 'The specified society does not exist', [], 404);
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO events (society_id, created_by, title, venue, category,
                    start_datetime, end_datetime, reg_deadline, capacity, fee_type, fee_amount, status)
                 VALUES (:society_id, :created_by, :title, :venue, :category,
                    :start_datetime, :end_datetime, :reg_deadline, :capacity, :fee_type, :fee_amount, :status)'
            );
            $stmt->execute([
                'society_id' => $societyId,
                'created_by' => $createdBy,
                'title' => $title,
                'venue' => $venue,
                'category' => $category,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'reg_deadline' => $regDeadline,
                'capacity' => $capacity,
                'fee_type' => $feeType,
                'fee_amount' => $feeAmount,
                // Always pending_approval - this scaffold has no draft state.
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
            'SELECT id, title, venue, category, start_datetime, capacity, status
             FROM events WHERE created_by = :created_by ORDER BY created_at DESC'
        );
        $stmt->execute(['created_by' => $createdBy]);

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

    private function errorResponse(Response $response, string $code, string $message, array $fields, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'fields' => $fields],
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}