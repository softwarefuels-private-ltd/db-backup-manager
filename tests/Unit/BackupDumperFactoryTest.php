<?php

namespace Tests\Unit;

use App\Models\DatabaseConnection;
use App\Services\BackupDumperFactory;
use App\Services\SystemRequirementService;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MongoDb;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Tests\TestCase;

class BackupDumperFactoryTest extends TestCase
{
    public function test_it_creates_mysql_dumper(): void
    {
        $conn = new DatabaseConnection([
            'name' => 'Production MySQL',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'myapp',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        $this->assertInstanceOf(MySql::class, $dumper);
        $this->assertEquals('myapp', $dumper->getDbName());
    }

    public function test_it_creates_pgsql_dumper(): void
    {
        $conn = new DatabaseConnection([
            'name' => 'Analytics Postgres',
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'analytics',
            'username' => 'postgres',
            'password' => 'secret',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        $this->assertInstanceOf(PostgreSql::class, $dumper);
        $this->assertEquals('analytics', $dumper->getDbName());
    }

    public function test_it_creates_sqlite_dumper(): void
    {
        $conn = new DatabaseConnection([
            'name' => 'Local SQLite',
            'driver' => 'sqlite',
            'database_path' => '/tmp/test.sqlite',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        $this->assertInstanceOf(Sqlite::class, $dumper);
        $this->assertEquals('/tmp/test.sqlite', $dumper->getDbName());
    }

    public function test_it_creates_mongodb_dumper(): void
    {
        $conn = new DatabaseConnection([
            'name' => 'Logs Mongo',
            'driver' => 'mongodb',
            'host' => '127.0.0.1',
            'port' => 27017,
            'database' => 'logs',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        $this->assertInstanceOf(MongoDb::class, $dumper);
        $this->assertEquals('logs', $dumper->getDbName());
    }

    public function test_it_creates_mariadb_dumper_when_driver_is_mariadb(): void
    {
        $mockReqService = $this->createMock(SystemRequirementService::class);
        $mockReqService->method('findBinary')
            ->willReturnCallback(function (string $name) {
                return $name === 'mariadb-dump' ? '/usr/bin/mariadb-dump' : null;
            });
        $this->app->instance(SystemRequirementService::class, $mockReqService);

        $conn = new DatabaseConnection([
            'name' => 'MariaDB Database',
            'driver' => 'mariadb',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test_mariadb',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        $this->assertInstanceOf(MariaDb::class, $dumper);
        $this->assertEquals('test_mariadb', $dumper->getDbName());
    }

    public function test_it_falls_back_to_mariadbdump_when_mysqldump_is_missing(): void
    {
        $mockReqService = $this->createMock(SystemRequirementService::class);
        $mockReqService->method('findBinary')
            ->willReturnCallback(function (string $name) {
                // Simulate mysqldump missing, but mariadb-dump installed
                return $name === 'mariadb-dump' ? 'C:\Program Files\MariaDB 11.4\bin\mariadb-dump.exe' : null;
            });
        $this->app->instance(SystemRequirementService::class, $mockReqService);

        $conn = new DatabaseConnection([
            'name' => 'MySQL Connection Using MariaDB Dump',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'legacy_mysql',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $dumper = BackupDumperFactory::make($conn);
        // Should fall back to MariaDb dumper class because mariadb-dump binary is executed
        $this->assertInstanceOf(MariaDb::class, $dumper);
        $this->assertEquals('legacy_mysql', $dumper->getDbName());
    }
}
