<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\JwtHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PDOException;

class AuthController
{
    // POST /api/auth/register
    // Public endpoint - creates a new attendee account.
    // (Organiser/faculty_admin accounts are assigned separately by an
    // existing faculty_admin via the societies/organisers endpoint,
    // not through public self-registration - matches PR1's role model.)
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $matricNo = trim($data['matric_no'] ?? '') ?: null;
        $phone = trim($data['phone'] ?? '') ?: null;

        // Server-side validation - the client validates too, but the
        // server must never trust the client (project brief requirement)
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Validation failed', $errors, 422);
        }

        $db = Database::getConnection();

        // Check for existing email before insert, so we can return a clear
        // 409 Conflict instead of a generic DB constraint error
        $checkStmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetch()) {
            return $this->errorResponse($response, 'EMAIL_TAKEN', 'An account with this email already exists', [], 409);
        }

        // bcrypt via PHP's password_hash() - this is what the project brief
        // means by "hashed passwords (e.g., bcrypt/Argon2)"
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $db->prepare(
                'INSERT INTO users (name, email, password_hash, role, matric_no, phone)
                 VALUES (:name, :email, :password_hash, :role, :matric_no, :phone)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                // Public self-registration always creates an attendee.
                'role' => 'attendee',
                'matric_no' => $matricNo,
                'phone' => $phone,
            ]);

            $userId = (int) $db->lastInsertId();
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'DB_ERROR', 'Could not create account', [], 500);
        }

        $token = JwtHelper::generateToken($userId, $email, 'attendee');

        return $this->successResponse($response, [
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'attendee',
            ],
        ], 'Account created successfully', 201);
    }

    // Shared helper for the project's success response convention
    // (per PR1 API contract A.5: { "success": true, "data": {...}, "message": "..." })
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

    // Shared helper for the project's error response convention
    // (per PR1 API contract A.5: { "success": false, "error": { "code", "message", "fields" } })
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