<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Exception;
use Illuminate\Support\Facades\Cache;
use PDO;
use Throwable;

class DatabaseHealthService
{
    /**
     * Test the health and connectivity of a database connection.
     *
     * @return array{
     *     status: 'healthy'|'unhealthy',
     *     healthy: bool,
     *     latency_ms: float|null,
     *     message: string,
     *     checked_at: string
     * }
     */
    public function check(DatabaseConnection $connection, bool $force = false): array
    {
        $cacheKey = "db_health_{$connection->id}";

        if (! $force && Cache::has($cacheKey)) {
            /** @var array{status: 'healthy'|'unhealthy', healthy: bool, latency_ms: float|null, message: string, checked_at: string} $cached */
            $cached = Cache::get($cacheKey);

            return $cached;
        }

        $start = microtime(true);
        $driver = strtolower($connection->driver);

        try {
            match ($driver) {
                'mysql', 'mariadb' => $this->testMysql($connection),
                'pgsql', 'postgres', 'postgresql' => $this->testPostgres($connection),
                'sqlite' => $this->testSqlite($connection),
                'mongodb', 'mongo' => $this->testMongo($connection),
                default => throw new Exception("Unsupported driver: {$driver}"),
            };

            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            $result = [
                'status' => 'healthy',
                'healthy' => true,
                'latency_ms' => $latencyMs,
                'message' => "Connection to [{$connection->name}] ({$driver}) succeeded",
                'checked_at' => now()->toIso8601String(),
            ];

            Cache::put($cacheKey, $result, 60);

            return $result;
        } catch (Throwable $e) {
            $result = [
                'status' => 'unhealthy',
                'healthy' => false,
                'latency_ms' => null,
                'message' => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];

            Cache::put($cacheKey, $result, 15);

            return $result;
        }
    }

    /**
     * Forget cached health check for a given connection.
     */
    public function forgetCache(int $connectionId): void
    {
        Cache::forget("db_health_{$connectionId}");
    }

    /**
     * Test MySQL / MariaDB connection.
     */
    protected function testMysql(DatabaseConnection $connection): void
    {
        $host = $connection->host ?: '127.0.0.1';
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }
        $port = (int) ($connection->port ?: 3306);

        $this->testSocketConnection($host, $port, 2);

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$connection->database}",
            $connection->username ?? '',
            $connection->password ?? '',
            [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $pdo->query('SELECT 1');
    }

    /**
     * Test PostgreSQL connection.
     */
    protected function testPostgres(DatabaseConnection $connection): void
    {
        $host = $connection->host ?: '127.0.0.1';
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }
        $port = (int) ($connection->port ?: 5432);

        $this->testSocketConnection($host, $port, 2);

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$connection->database}",
            $connection->username ?? '',
            $connection->password ?? '',
            [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $pdo->query('SELECT 1');
    }

    /**
     * Test SQLite database file and access.
     */
    protected function testSqlite(DatabaseConnection $connection): void
    {
        $path = $connection->database_path ?: $connection->database;

        if (empty($path)) {
            throw new Exception('SQLite database path is not configured.');
        }

        if (! file_exists($path)) {
            throw new Exception("SQLite file does not exist at [{$path}].");
        }

        if (! is_readable($path)) {
            throw new Exception("SQLite file is not readable at [{$path}].");
        }

        $pdo = new PDO("sqlite:{$path}", null, null, [
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->query('SELECT 1');
    }

    /**
     * Test MongoDB socket reachability.
     */
    protected function testMongo(DatabaseConnection $connection): void
    {
        $host = $connection->host ?: '127.0.0.1';
        $port = (int) ($connection->port ?: 27017);

        $this->testSocketConnection($host, $port, 2);
    }

    /**
     * Test network socket connection with timeout.
     */
    protected function testSocketConnection(string $host, int $port, int $timeoutSeconds = 2): void
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);

        if (! is_resource($connection)) {
            throw new Exception("Could not reach host {$host}:{$port} ({$errstr})");
        }

        fclose($connection);
    }
}
