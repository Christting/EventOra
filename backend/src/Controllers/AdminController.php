<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PDOException;

// Handles the Faculty Admin Approval workflow (PR1 Appendix A.2 + Section 5.1
// "Faculty Admin Approval Workflow"). Every method here sits behind both
// JwtMiddleware and RoleMiddleware(['faculty_admin']) at the route level
// (see public/index.php), so by the time code reaches this controller we
// already know: (a) the request carries a valid token, and (b) the user's
// role is faculty_admin. This controller only needs to worry about the
// business logic of the approval workflow itself.
class AdminController
{
    // GET /api/admin/events/pending
    // Lists all events currently awaiting approval, per PR1 5.1:
    // "Approval queue dashboard lists all pending events with: society name,
    // event title, date, category, capacity, and submission timestamp."
    public function listPendingEvents(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        // JOIN societies so we can return the society name directly -
        // the approval queue UI needs "society name", not just a raw
        // society_id the admin would have to look up separately.
        // created_at doubles as the "submission timestamp" PR1 asks for,
        // since events are only created once an organiser submits them.
        $stmt = $db->prepare(
            'SELECT
                e.id,
                e.title,
                e.category,
                e.capacity,
                e.start_datetime,
                e.end_datetime,
                e.created_at AS submitted_at,
                s.id AS society_id,
                s.name AS society_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.status = :status
             ORDER BY e.created_at ASC'
        );
        $stmt->execute(['status' => 'pending_approval']);
        $pendingEvents = $stmt->fetchAll();

        return $this->successResponse($response, $pendingEvents, null, 200);
    }

    // Shared lookup used by both approveEvent() and rejectEvent() - fetches
    // only the columns the state-transition check actually needs, rather
    // than the full event row.
    private function findEventOrNull(PDO $db, int $eventId): ?array
    {
        $stmt = $db->prepare('SELECT id, status FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    // Centralised state-transition validation for approve/reject.
    //
    // This deliberately distinguishes TWO different failure cases instead
    // of lumping them into one generic error, because PR1 Appendix A.6
    // defines them with different meanings:
    //
    //   - 400 Bad Request: "Malformed request or invalid state transition."
    //     Used when the event was never eligible for approval in the first
    //     place (draft, completed, or cancelled) - the admin is trying to
    //     approve/reject something that was never submitted for review,
    //     or has already finished its lifecycle entirely.
    //
    //   - 409 Conflict: "...repeated QR check-in" (i.e. the general pattern
    //     of repeating an action that has already happened once).
    //     Used when the event was already published or rejected - meaning
    //     a faculty_admin decision was already recorded for it, and
    //     approving/rejecting it again would create a duplicate, misleading
    //     entry in event_approvals for an event that already has a decision.
    //
    // Returns null if the event is in 'pending_approval' (safe to proceed),
    // or a ready-to-return error Response otherwise.
    private function checkPendingOrFail(Response $response, array $event): ?Response
    {
        if ($event['status'] === 'pending_approval') {
            return null;
        }

        $alreadyDecided = in_array($event['status'], ['published', 'rejected'], true);

        if ($alreadyDecided) {
            return $this->errorResponse(
                $response,
                'ALREADY_REVIEWED',
                "This event has already been reviewed (current status: {$event['status']})",
                [],
                409
            );
        }

        // draft, completed, or cancelled - never entered, or already left,
        // the approval pipeline entirely
        return $this->errorResponse(
            $response,
            'INVALID_STATE_TRANSITION',
            "Event with status '{$event['status']}' is not awaiting approval",
            [],
            400
        );
    }

    // Same success/error response helpers as AuthController, kept
    // consistent with the project's API contract (PR1 A.5):
    // success -> { "success": true, "data": {...}, "message": "..." }
    // error   -> { "success": false, "error": { "code", "message", "fields" } }
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