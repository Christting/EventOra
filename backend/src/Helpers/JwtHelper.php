<?php

declare(strict_types=1);

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    // Builds and signs a JWT for a given user.
    // Called right after a successful login or register.
    public static function generateToken(int $userId, string $email, string $role): string
    {
        $secret = $_ENV['JWT_SECRET'];
        $expiry = (int) ($_ENV['JWT_EXPIRY'] ?? 86400);

        $issuedAt = time();
        $expiresAt = $issuedAt + $expiry;

        // "sub" (subject) holds the user id, "role" is custom data we need
        // for role-based route checks later (organiser / faculty_admin / attendee)
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    // Decodes and verifies a token. Throws an exception if it's invalid
    // or expired - the middleware below is what catches that exception.
    public static function decodeToken(string $token): array
    {
        $secret = $_ENV['JWT_SECRET'];
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));

        // JWT::decode returns a stdClass, casting to array is easier to work with
        return (array) $decoded;
    }
}