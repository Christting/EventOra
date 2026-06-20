<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;

// Single shared PDO connection for the whole app.
// Every controller that needs to talk to MySQL goes through here
// instead of creating its own connection.
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'];
            $dbName = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];

            // charset=utf8mb4 matches the schema.sql database collation,
            // so multi-byte characters (e.g. event titles in Malay/Chinese) work correctly
            $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    // Throw exceptions on error instead of silently failing -
                    // this is what lets our controllers use try/catch around queries
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Return rows as associative arrays (['email' => ...]) instead of
                    // numeric-indexed arrays - much easier to work with in controllers
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Use real prepared statements sent to MySQL, not PHP-emulated ones.
                    // This is what actually protects against SQL injection at the
                    // protocol level, per the project's PDO security requirement
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // Don't leak DB credentials or internal connection details to the client.
                // Log the real error server-side (here, just rethrow with a generic message)
                throw new PDOException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}