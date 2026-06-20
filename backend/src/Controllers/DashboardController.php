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