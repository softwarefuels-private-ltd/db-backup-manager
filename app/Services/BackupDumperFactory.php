<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use InvalidArgumentException;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MongoDb;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\DbDumper;

class BackupDumperFactory
{
    /**
     * Build a configured Spatie DbDumper instance based on the database connection model.
     */
    public static function make(DatabaseConnection $conn): DbDumper
    {
        $reqService = app(SystemRequirementService::class);
        $driver = strtolower($conn->driver);

        return match ($driver) {
            'mysql', 'mariadb' => static::makeMySqlDumper($conn, $reqService),
            'pgsql', 'postgres', 'postgresql' => static::makePostgreSqlDumper($conn, $reqService),
            'sqlite' => static::makeSqliteDumper($conn, $reqService),
            'mongodb', 'mongo' => static::makeMongoDbDumper($conn, $reqService),
            default => throw new InvalidArgumentException("Unsupported database driver: [{$conn->driver}]"),
        };
    }

    protected static function makeMySqlDumper(DatabaseConnection $conn, SystemRequirementService $reqService): MySql
    {
        $hasMysqlDump = $reqService->findBinary('mysqldump');
        $hasMariaDbDump = $reqService->findBinary('mariadb-dump');

        $driver = strtolower($conn->driver);

        // If driver is mariadb and mariadb-dump is available, or if mysqldump is missing but mariadb-dump is available
        $useMariaDb = ($driver === 'mariadb' && $hasMariaDbDump) || (! $hasMysqlDump && $hasMariaDbDump);

        $dumper = $useMariaDb ? MariaDb::create() : MySql::create();

        $host = $conn->host ?: '127.0.0.1';
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $dumper->setHost($host)
            ->setPort($conn->port ?? 3306)
            ->setDbName($conn->database ?? '')
            ->setUserName($conn->username ?? 'root')
            ->setPassword($conn->password ?? '');

        $bin = $useMariaDb ? $hasMariaDbDump : ($hasMysqlDump ?: $hasMariaDbDump);
        if ($bin) {
            $dumper->setDumpBinaryPath(dirname($bin));
        }

        return $dumper;
    }

    protected static function makePostgreSqlDumper(DatabaseConnection $conn, SystemRequirementService $reqService): PostgreSql
    {
        $host = $conn->host ?: '127.0.0.1';
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $dumper = PostgreSql::create()
            ->setHost($host)
            ->setPort($conn->port ?? 5432)
            ->setDbName($conn->database ?? '')
            ->setUserName($conn->username ?? 'postgres')
            ->setPassword($conn->password ?? '');

        $bin = $reqService->findBinary('pg_dump');
        if ($bin) {
            $dumper->setDumpBinaryPath(dirname($bin));
        }

        return $dumper;
    }

    protected static function makeSqliteDumper(DatabaseConnection $conn, SystemRequirementService $reqService): Sqlite
    {
        $dumper = Sqlite::create()
            ->setDbName($conn->database_path ?? $conn->database ?? database_path('database.sqlite'));

        $bin = $reqService->findBinary('sqlite3');
        if ($bin) {
            $dumper->setDumpBinaryPath(dirname($bin));
        }

        return $dumper;
    }

    protected static function makeMongoDbDumper(DatabaseConnection $conn, SystemRequirementService $reqService): MongoDb
    {
        $dumper = MongoDb::create()
            ->setHost($conn->host ?? '127.0.0.1')
            ->setPort($conn->port ?? 27017)
            ->setDbName($conn->database ?? '')
            ->setUserName($conn->username ?? '')
            ->setPassword($conn->password ?? '')
            ->setAuthenticationDatabase($conn->extra_options['auth_database'] ?? 'admin');

        $bin = $reqService->findBinary('mongodump');
        if ($bin) {
            $dumper->setDumpBinaryPath(dirname($bin));
        }

        return $dumper;
    }
}
