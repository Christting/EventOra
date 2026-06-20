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
        $role = $data['role'] ?? 'attendee';
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

        // SECURITY: public self-registration may only create attendee or
        // organiser accounts. faculty_admin is a privileged approval role
        // and must never be selectable here - even if the client sends it,
        // we reject it server-side rather than silently downgrading it,
        // so a misconfigured frontend fails loudly instead of quietly.
        if (!in_array($role, ['attendee', 'organiser'], true)) {
            $errors['role'] = 'Role must be either attendee or organiser';
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
                'role' => $role,
                'matric_no' => $matricNo,
                'phone' => $phone,
            ]);

            $userId = (int) $db->lastInsertId();
        } catch (PDOException $e) {
            return $this->errorResponse($response, 'DB_ERROR', 'Could not create account', [], 500);
        }

        $token = JwtHelper::generateToken($userId, $email, $role);

        return $this->successResponse($response, [
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ],
        ], 'Account created successfully', 201);
    }

    // POST /api/auth/login
    // Public endpoint - verifies credentials and returns a signed JWT.
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'Email and password are required', [], 422);
        }

        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Deliberately vague error message here - "Invalid email or password"
        // rather than "email not found" / "wrong password" separately.
        // This prevents an attacker from using the login endpoint to
        // enumerate which emails are registered.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->errorResponse($response, 'INVALID_CREDENTIALS', 'Invalid email or password', [], 401);
        }

        $token = JwtHelper::generateToken((int) $user['id'], $user['email'], $user['role']);

        return $this->successResponse($response, [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ], 'Login successful', 200);
    }

    // POST /api/auth/refresh
    // Authenticated endpoint (sits behind JwtMiddleware) - issues a fresh
    // token with a reset expiry, using the user info JwtMiddleware already
    // decoded and attached to the request.
    public function refresh(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $newToken = JwtHelper::generateToken(
            (int) $user['sub'],
            $user['email'],
            $user['role']
        );

        return $this->successResponse($response, [
            'token' => $newToken,
        ], 'Token refreshed', 200);
    }

    // POST /api/auth/logout
    // Authenticated endpoint. JWTs are stateless - the server doesn't keep
    // a session to destroy - so "logout" here just confirms the request
    // was authenticated. The actual logout action is the client discarding
    // its stored token (matches the PR1 API contract description verbatim:
    // "client discards the stored JWT").
    public function logout(Request $request, Response $response): Response
    {
        return $this->successResponse($response, null, 'Logged out successfully', 200);
    }

    // GET /api/me
    // Authenticated endpoint - returns the current user's profile, role,
    // and society membership (per PR1 API contract A.1).
    public function me(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $userId = (int) $authUser['sub'];

        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT id, name, email, role, matric_no, phone, avatar_url, created_at
             FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            return $this->errorResponse($response, 'USER_NOT_FOUND', 'User no longer exists', [], 404);
        }

        // Society membership - relevant for organisers, empty array for
        // attendees/faculty_admin who aren't in society_members at all
        $memberStmt = $db->prepare(
            'SELECT sm.society_id, s.name AS society_name, sm.role AS society_role
             FROM society_members sm
             JOIN societies s ON s.id = sm.society_id
             WHERE sm.user_id = :user_id'
        );
        $memberStmt->execute(['user_id' => $userId]);
        $memberships = $memberStmt->fetchAll();

        $profile['society_memberships'] = $memberships;

        return $this->successResponse($response, $profile, null, 200);
    }

    // PUT /api/me
    // Authenticated endpoint - updates the current user's permitted
    // profile fields. Deliberately does NOT allow updating email or role
    // here - role changes go through the faculty_admin society/organiser
    // endpoints, and email changes would need re-verification (out of
    // scope for this project).
    public function updateMe(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');
        $userId = (int) $authUser['sub'];

        $data = $request->getParsedBody();

        // Build the update dynamically based on which fields were actually
        // sent, so a partial PUT (e.g. just { "phone": "..." }) doesn't
        // overwrite other fields with null
        $allowedFields = ['name', 'matric_no', 'phone', 'avatar_url'];
        $updates = [];
        $params = ['id' => $userId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = trim((string) $data[$field]) ?: null;
            }
        }

        if (empty($updates)) {
            return $this->errorResponse($response, 'VALIDATION_ERROR', 'No valid fields provided to update', [], 422);
        }

        $db = Database::getConnection();
        $setClause = implode(', ', $updates);

        $stmt = $db->prepare("UPDATE users SET {$setClause} WHERE id = :id");
        $stmt->execute($params);

        return $this->successResponse($response, null, 'Profile updated successfully', 200);
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