<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

// Handles the Organiser Dashboard (PR1 Appendix A.4 + Section 5.1 /
// Section 6.2.1). Sits behind JwtMiddleware + RoleMiddleware(['organiser'])
// at the route level (see public/index.php), so this controller can
// assume the caller is already authenticated and holds the organiser role.
class DashboardController
{
    // GET /api/dashboard/organiser
    // Returns event totals, registration counts, attendance rate,
    // capacity use, and average rating - scoped ONLY to events belonging
    // to societies this organiser is a member of (PR1's security model:
    // "An organiser can only manage events belonging to societies they
    // are a member of; cross-society access is rejected with HTTP 403").
    //
    // Note this isn't a 403 case though - there's no "other organiser's
    // data" being requested here. The society_members lookup below is
    // simply how we find OUR OWN scope, the same way GET /api/me already
    // looks up the caller's own profile by their own id.
    public function organiserDashboard(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $organiserId = (int) $authUser['sub'];

        $db = Database::getConnection();

        $societyIds = $this->getOrganiserSocietyIds($db, $organiserId);

        // An organiser who isn't (yet) attached to any society has
        // nothing to report on. Returning all-zero stats here is more
        // correct than a 404 or 403 - the organiser account itself is
        // valid, there's just no data to aggregate yet.
        if (empty($societyIds)) {
            return $this->successResponse($response, $this->emptyDashboard(), null, 200);
        }

        $eventIds = $this->getEventIdsForSocieties($db, $societyIds);

        $eventTotals = $this->getEventTotalsByStatus($db, $societyIds);

        // No events at all yet (e.g. organiser just joined a brand new
        // society) - skip the registration/attendance queries entirely,
        // since they'd all just be IN ([]) which is invalid SQL anyway.
        if (empty($eventIds)) {
            return $this->successResponse($response, [
                'event_totals' => $eventTotals,
                'total_events' => 0,
                'total_registrations' => 0,
                'confirmed_registrations' => 0,
                'attendance' => [
                    'checked_in' => 0,
                    'rate_percent' => null,
                ],
                'capacity' => [
                    'total_capacity' => 0,
                    'confirmed_count' => 0,
                    'use_percent' => null,
                ],
                // TODO: replace with a real query once the `feedback`
                // table exists (Should-Have feature, not yet built -
                // see PR1 5.2). Until then there is nothing to average.
                'average_rating' => null,
            ], null, 200);
        }

        // Temp return to satisfy build before adding registration/capacity logic
        return $this->successResponse($response, $this->emptyDashboard(), null, 200);
    }

    // Finds every society this organiser belongs to. Per the data
    // dictionary, society_members.role can be 'organiser' or
    // 'co_organiser' - both count as membership for dashboard purposes,
    // so we don't filter by role here.
    private function getOrganiserSocietyIds(PDO $db, int $organiserId): array
    {
        $stmt = $db->prepare(
            'SELECT society_id FROM society_members WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $organiserId]);

        // fetchAll(PDO::FETCH_COLUMN) pulls just the single column out as
        // a flat array (e.g. [1, 4]) instead of [['society_id' => 1], ...]
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Finds every event belonging to the given societies, regardless of
    // status - the dashboard should show drafts and pending events too,
    // not just published ones, since the organiser needs visibility into
    // their whole pipeline.
    private function getEventIdsForSocieties(PDO $db, array $societyIds): array
    {
        $placeholders = $this->buildPlaceholders($societyIds);

        $stmt = $db->prepare(
            "SELECT id FROM events WHERE society_id IN ({$placeholders})"
        );
        $stmt->execute($societyIds);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Counts events per status (draft, pending_approval, published,
    // completed, rejected, cancelled), always returning all six keys
    // even if some are zero - this makes the response predictable for
    // the frontend (PR1 5.1: "Society dashboard... Status lifecycle").
    private function getEventTotalsByStatus(PDO $db, array $societyIds): array
    {
        $placeholders = $this->buildPlaceholders($societyIds);

        $stmt = $db->prepare(
            "SELECT status, COUNT(*) AS total
             FROM events
             WHERE society_id IN ({$placeholders})
             GROUP BY status"
        );
        $stmt->execute($societyIds);
        $rows = $stmt->fetchAll();

        // Start every possible status at 0 so the frontend never has to
        // guess whether a missing key means "zero" or "not loaded yet".
        $totals = [
            'draft' => 0,
            'pending_approval' => 0,
            'published' => 0,
            'completed' => 0,
            'rejected' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $totals[$row['status']] = (int) $row['total'];
        }

        return $totals;
    }

    // Shared percentage helper. Returns null (not 0) when the
    // denominator is zero, because "0% capacity used" and "we don't
    // have a capacity to measure against" are different facts - a null
    // tells the frontend to show "N/A" instead of a misleading "0%".
    private function safePercentage(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    // attendance.rate_percent uses the same safePercentage() logic but
    // against confirmed registrations as the denominator, so it's
    // computed here in the main method rather than duplicated inside
    // getAttendanceStats() - keeping each private method focused on
    // fetching its own raw numbers, with percentage math centralised.
    private function getAttendanceRatePercent(int $checkedIn, int $confirmedCount): ?float
    {
        return $this->safePercentage($checkedIn, $confirmedCount);
    }

    // Builds a comma-separated list of "?" placeholders matching the
    // size of the given array, for use in a SQL "IN (...)" clause.
    // PDO has no native way to bind an array directly into IN(), so this
    // is the standard prepared-statement-safe way to do it: build
    // "?, ?, ?" for however many items there are, then pass the array
    // straight to execute() positionally.
    private function buildPlaceholders(array $items): string
    {
        return implode(', ', array_fill(0, count($items), '?'));
    }

    // Returns an all-zero/empty dashboard shape for an organiser with no
    // society membership yet, so the frontend always receives the same
    // response shape regardless of how little data exists.
    private function emptyDashboard(): array
    {
        return [
            'event_totals' => [
                'draft' => 0,
                'pending_approval' => 0,
                'published' => 0,
                'completed' => 0,
                'rejected' => 0,
                'cancelled' => 0,
            ],
            'total_events' => 0,
            'total_registrations' => 0,
            'confirmed_registrations' => 0,
            'attendance' => [
                'checked_in' => 0,
                'rate_percent' => null,
            ],
            'capacity' => [
                'total_capacity' => 0,
                'confirmed_count' => 0,
                'use_percent' => null,
            ],
            'average_rating' => null,
        ];
    }

    // Same success/error response helpers as AuthController and
    // AdminController, kept consistent with the project's API contract
    // (PR1 A.5).
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
}