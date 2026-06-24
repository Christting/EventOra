<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\NotificationService;
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
                e.status,
                e.title,
                e.description,
                e.venue,
                e.category,
                e.capacity,
                e.fee_type,
                e.fee_amount,
                e.start_datetime,
                e.end_datetime,
                e.created_at,
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

    public function showEvent(Request $request, Response $response, array $args): Response
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT
                e.id,
                e.status,
                e.title,
                e.description,
                e.venue,
                e.category,
                e.capacity,
                e.fee_type,
                e.fee_amount,
                e.start_datetime,
                e.end_datetime,
                e.reg_deadline,
                e.created_at,
                s.id AS society_id,
                s.name AS society_name,
                u.name AS created_by_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             JOIN users u ON u.id = e.created_by
             WHERE e.id = :id'
        );
        $stmt->execute(['id' => (int) $args['id']]);
        $event = $stmt->fetch();

        if (!$event) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }

        return $this->successResponse($response, $event, null, 200);
    }

    // POST /api/admin/events/{id}/approve
    // Approves a pending event: event.status -> published, and a record
    // is written to event_approvals with decision = 'approved' so there
    // is a permanent audit trail of who approved it and when
    // (PR1 5.1: "Approve action: event status transitions to published
    // and becomes visible on the public listing page.")
    public function approveEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $authUser = $request->getAttribute('user');
        $reviewerId = (int) $authUser['sub'];

        $db = Database::getConnection();

        $event = $this->findEventOrNull($db, $eventId);

        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }

        // Centralised state-transition check - see checkPendingOrFail()
        // below for why this is split into 400 vs 409 instead of a single
        // catch-all error.
        $stateError = $this->checkPendingOrFail($response, $event);
        if ($stateError !== null) {
            return $stateError;
        }

        // The JWT payload only carries sub/email/role by design (kept
        // deliberately small, and a user's name can change without
        // invalidating their existing token). So to return a human-
        // readable reviewer name to the frontend, we look it up here
        // rather than adding it to the JWT itself.
        $reviewerName = $this->getUserName($db, $reviewerId);

        try {
            $db->beginTransaction();

            $updateStmt = $db->prepare(
                'UPDATE events SET status = :status WHERE id = :id'
            );
            $updateStmt->execute([
                'status' => 'published',
                'id' => $eventId,
            ]);

            // reason is intentionally NULL for an approval - PR1's data
            // dictionary marks event_approvals.reason as
            // "Rejection reason; required if rejected, nullable if approved"
            $approvalStmt = $db->prepare(
                'INSERT INTO event_approvals (event_id, reviewed_by, decision, reason)
                 VALUES (:event_id, :reviewed_by, :decision, NULL)'
            );
            $approvalStmt->execute([
                'event_id' => $eventId,
                'reviewed_by' => $reviewerId,
                'decision' => 'approved',
            ]);

            NotificationService::create(
                (int) $event['created_by'],
                'event_approved',
                'Event approved',
                "Your event '{$event['title']}' has been approved and published.",
                $eventId
            );

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            return $this->errorResponse($response, 'DB_ERROR', 'Could not approve event', [], 500);
        }

        return $this->successResponse(
            $response,
            [
                'event_id' => $eventId,
                'status' => 'published',
                'reviewed_by' => [
                    'id' => $reviewerId,
                    'name' => $reviewerName,
                ],
            ],
            'Event approved and published',
            200
        );
    }

    // POST /api/admin/events/{id}/reject
    // Rejects a pending event: event.status -> rejected, and a record is
    // written to event_approvals with decision = 'rejected' AND the
    // mandatory reason. PR1 5.1 is explicit that the reject action
    // "triggers a modal requiring the admin to enter a rejection reason" -
    // so on the backend, a missing/empty reason must be rejected with 422,
    // never silently defaulted to an empty string.
    public function rejectEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $authUser = $request->getAttribute('user');
        $reviewerId = (int) $authUser['sub'];

        $data = $request->getParsedBody();
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            return $this->errorResponse(
                $response,
                'VALIDATION_ERROR',
                'Validation failed',
                ['reason' => 'A rejection reason is required'],
                422
            );
        }

        $db = Database::getConnection();

        $event = $this->findEventOrNull($db, $eventId);

        if ($event === null) {
            return $this->errorResponse($response, 'EVENT_NOT_FOUND', 'Event not found', [], 404);
        }

        $stateError = $this->checkPendingOrFail($response, $event);
        if ($stateError !== null) {
            return $stateError;
        }

        // Same reasoning as approveEvent() - JWT doesn't carry name, so we
        // look it up here to give the frontend a human-readable reviewer.
        $reviewerName = $this->getUserName($db, $reviewerId);

        try {
            $db->beginTransaction();

            $updateStmt = $db->prepare(
                'UPDATE events SET status = :status WHERE id = :id'
            );
            $updateStmt->execute([
                'status' => 'rejected',
                'id' => $eventId,
            ]);

            $approvalStmt = $db->prepare(
                'INSERT INTO event_approvals (event_id, reviewed_by, decision, reason)
                 VALUES (:event_id, :reviewed_by, :decision, :reason)'
            );
            $approvalStmt->execute([
                'event_id' => $eventId,
                'reviewed_by' => $reviewerId,
                'decision' => 'rejected',
                'reason' => $reason,
            ]);

            NotificationService::create(
                (int) $event['created_by'],
                'event_rejected',
                'Event rejected',
                "Your event '{$event['title']}' was rejected. Reason: {$reason}",
                $eventId
            );

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            return $this->errorResponse($response, 'DB_ERROR', 'Could not reject event', [], 500);
        }

        return $this->successResponse(
            $response,
            [
                'event_id' => $eventId,
                'status' => 'rejected',
                'reason' => $reason,
                'reviewed_by' => [
                    'id' => $reviewerId,
                    'name' => $reviewerName,
                ],
            ],
            'Event rejected',
            200
        );
    }

    // Shared lookup used by both approveEvent() and rejectEvent() - fetches
    // only the columns the state-transition check actually needs, rather
    // than the full event row.
    private function findEventOrNull(PDO $db, int $eventId): ?array
    {
        $stmt = $db->prepare('SELECT id, status, title, created_by FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    // Looks up a user's display name by id. Used to attach a human-
    // readable "reviewed_by" name to the approve/reject response, since
    // the JWT payload itself only contains sub/email/role (see JwtHelper).
    private function getUserName(PDO $db, int $userId): ?string
    {
        $stmt = $db->prepare('SELECT name FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        return $user['name'] ?? null;
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
