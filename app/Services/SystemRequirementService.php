<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SystemRequirementService
{
    /**
     * Additional system paths to search for database dump binaries.
     *
     * @var list<string>
     */
    protected array $searchPaths = [
        // Unix / macOS paths
        '/opt/homebrew/bin',
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/sbin',
        '/sbin',
        '/usr/local/mysql/bin',
        '/opt/homebrew/opt/libpq/bin',
        '/opt/homebrew/opt/mysql-client/bin',
        '/opt/homebrew/opt/mongodb-community/bin',
        '/opt/lampp/bin',

        // Windows common paths (MySQL, MariaDB, XAMPP, Laragon, WAMP)
        'C:\\xampp\\mysql\\bin',
        'D:\\xampp\\mysql\\bin',
        'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin',
        'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin',
        'C:\\Program Files\\MariaDB 11.4\\bin',
        'C:\\Program Files\\MariaDB 11.3\\bin',
        'C:\\Program Files\\MariaDB 11.2\\bin',
        'C:\\Program Files\\MariaDB 10.11\\bin',
        'C:\\Program Files\\MariaDB 10.6\\bin',
        'C:\\laragon\\bin\\mysql\\current\\bin',
        'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin',
        'C:\\Program Files\\PostgreSQL\\17\\bin',
        'C:\\Program Files\\PostgreSQL\\16\\bin',
        'C:\\Program Files\\PostgreSQL\\15\\bin',
        'C:\\Program Files\\MongoDB\\Tools\\100\\bin',
    ];

    /**
     * Evaluate the server's requirements, drivers, and dump binaries.
     *
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $binaries = $this->checkBinaries();
        $extensions = $this->checkPhpExtensions();
        $engines = $this->evaluateEngines($binaries, $extensions);
        $systemInfo = $this->getSystemInfo();

        $readyCount = count(array_filter($engines, fn ($e) => $e['ready']));
        $totalCount = count($engines);

        return [
            'summary' => [
                'total_engines' => $totalCount,
                'ready_engines' => $readyCount,
                'all_ready' => $readyCount === $totalCount,
            ],
            'engines' => $engines,
            'binaries' => $binaries,
            'php_extensions' => $extensions,
            'system_info' => $systemInfo,
        ];
    }

    /**
     * Find the path to a specific binary if available.
     */
    public function findBinary(string $name): ?string
    {
        // 1. Direct environment variable override for specific tool (e.g. MYSQL_DUMP_PATH)
        $envKey = match ($name) {
            'mysqldump' => 'MYSQL_DUMP_PATH',
            'mariadb-dump' => 'MARIADB_DUMP_PATH',
            'pg_dump' => 'PG_DUMP_PATH',
            'mongodump' => 'MONGO_DUMP_PATH',
            'sqlite3' => 'SQLITE_DUMP_PATH',
            default => null,
        };

        if ($envKey && ($configuredPath = env($envKey))) {
            $resolved = $this->resolveDirectPath((string) $configuredPath, $name);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // 2. Generic dump binary directory override (e.g. DUMP_BINARY_PATH or DUMP_BINARY_DIR)
        $genericDir = env('DUMP_BINARY_PATH') ?: env('DUMP_BINARY_DIR');
        if ($genericDir) {
            $resolved = $this->resolveDirectPath((string) $genericDir, $name);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // 3. Fallback to ExecutableFinder across all configured search paths + system PATH
        $searchPaths = $this->searchPaths;
        if ($genericDir && is_dir((string) $genericDir)) {
            array_unshift($searchPaths, (string) $genericDir);
        }

        $finder = new ExecutableFinder;

        return $finder->find($name, null, $searchPaths);
    }

    /**
     * Resolve a direct path that could be either a file or a directory.
     */
    protected function resolveDirectPath(string $path, string $binaryName): ?string
    {
        $path = trim($path, " '\"\t\n\r");

        if (is_file($path)) {
            return $path;
        }

        if (is_dir($path)) {
            $base = rtrim($path, '/\\').DIRECTORY_SEPARATOR.$binaryName;
            if (is_file($base)) {
                return $base;
            }
            if (is_file($base.'.exe')) {
                return $base.'.exe';
            }
        }

        return null;
    }

    /**
     * Check individual command-line dump tools on the host.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function checkBinaries(): array
    {
        $tools = [
            'mysqldump' => [
                'name' => 'mysqldump',
                'engine' => 'MySQL / MariaDB',
                'description' => 'Exports MySQL & MariaDB schema and tables into SQL dump files.',
                'version_flag' => '--version',
            ],
            'mariadb-dump' => [
                'name' => 'mariadb-dump',
                'engine' => 'MariaDB',
                'description' => 'Official MariaDB utility for creating SQL backups.',
                'version_flag' => '--version',
            ],
            'pg_dump' => [
                'name' => 'pg_dump',
                'engine' => 'PostgreSQL',
                'description' => 'Extracts a PostgreSQL database into a script file or archive.',
                'version_flag' => '--version',
            ],
            'mongodump' => [
                'name' => 'mongodump',
                'engine' => 'MongoDB',
                'description' => 'Creates high performance BSON and gzip dumps of MongoDB databases.',
                'version_flag' => '--version',
            ],
            'sqlite3' => [
                'name' => 'sqlite3',
                'engine' => 'SQLite',
                'description' => 'SQLite shell utility used for vacuum backups and quick integrity checks.',
                'version_flag' => '--version',
            ],
            'gzip' => [
                'name' => 'gzip',
                'engine' => 'Compression',
                'description' => 'Compresses backup SQL exports on the fly to conserve storage space.',
                'version_flag' => '-V',
            ],
        ];

        $results = [];

        foreach ($tools as $key => $tool) {
            $path = $this->findBinary($tool['name']);
            $installed = $path !== null;
            $version = null;

            if ($installed) {
                $version = $this->extractBinaryVersion($path, $tool['version_flag']);
            }

            $results[$key] = [
                'name' => $tool['name'],
                'engine' => $tool['engine'],
                'description' => $tool['description'],
                'installed' => $installed,
                'path' => $path,
                'version' => $version,
            ];
        }

        return $results;
    }

    /**
     * Check relevant PHP drivers and extensions.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function checkPhpExtensions(): array
    {
        $exts = [
            'pdo_mysql' => [
                'name' => 'pdo_mysql',
                'engine' => 'MySQL / MariaDB',
                'description' => 'PHP Data Objects driver for MySQL and MariaDB connectivity & health checks.',
                'required' => true,
            ],
            'pdo_pgsql' => [
                'name' => 'pdo_pgsql',
                'engine' => 'PostgreSQL',
                'description' => 'PHP Data Objects driver for PostgreSQL connectivity & health checks.',
                'required' => true,
            ],
            'pdo_sqlite' => [
                'name' => 'pdo_sqlite',
                'engine' => 'SQLite',
                'description' => 'PHP Data Objects driver for local SQLite files.',
                'required' => true,
            ],
            'sockets' => [
                'name' => 'sockets',
                'engine' => 'MongoDB & Network',
                'description' => 'Low-level network socket interface used for MongoDB health probing.',
                'required' => true,
            ],
            'curl' => [
                'name' => 'curl',
                'engine' => 'Cloud Storage',
                'description' => 'Client URL library for AWS S3 and Azure Blob Storage REST APIs.',
                'required' => true,
            ],
            'zlib' => [
                'name' => 'zlib',
                'engine' => 'Compression',
                'description' => 'In-memory gzip compression and stream compression.',
                'required' => true,
            ],
            'openssl' => [
                'name' => 'openssl',
                'engine' => 'Security',
                'description' => 'Cryptographic functions for credential encryption and Azure HMAC signing.',
                'required' => true,
            ],
        ];

        $results = [];

        foreach ($exts as $key => $ext) {
            $loaded = extension_loaded($key);
            $results[$key] = [
                'name' => $ext['name'],
                'engine' => $ext['engine'],
                'description' => $ext['description'],
                'required' => $ext['required'],
                'installed' => $loaded,
            ];
        }

        return $results;
    }

    /**
     * Evaluate overall database engine dump readiness.
     *
     * @param  array<string, array<string, mixed>>  $binaries
     * @param  array<string, array<string, mixed>>  $extensions
     * @return array<string, array<string, mixed>>
     */
    protected function evaluateEngines(array $binaries, array $extensions): array
    {
        $mysqlCli = $binaries['mysqldump']['installed'] || $binaries['mariadb-dump']['installed'];
        $mysqlExt = $extensions['pdo_mysql']['installed'];
        $mysqlBinaryPath = $binaries['mysqldump']['path'] ?: $binaries['mariadb-dump']['path'];
        $mysqlBinaryName = $binaries['mysqldump']['installed'] ? 'mysqldump' : ($binaries['mariadb-dump']['installed'] ? 'mariadb-dump' : 'mysqldump');
        $mysqlVersion = $binaries['mysqldump']['version'] ?: $binaries['mariadb-dump']['version'];

        $pgsqlCli = $binaries['pg_dump']['installed'];
        $pgsqlExt = $extensions['pdo_pgsql']['installed'];

        $mongoCli = $binaries['mongodump']['installed'];
        $mongoExt = $extensions['sockets']['installed'];

        $sqliteCli = $binaries['sqlite3']['installed'];
        $sqliteExt = $extensions['pdo_sqlite']['installed'];

        return [
            'mysql' => [
                'id' => 'mysql',
                'name' => 'MySQL / MariaDB',
                'ready' => $mysqlCli && $mysqlExt,
                'php_driver' => [
                    'name' => 'pdo_mysql',
                    'installed' => $mysqlExt,
                ],
                'cli_tool' => [
                    'name' => $mysqlBinaryName,
                    'installed' => $mysqlCli,
                    'path' => $mysqlBinaryPath,
                    'version' => $mysqlVersion,
                ],
                'install_hints' => [
                    'macos' => 'brew install mysql-client',
                    'ubuntu' => 'sudo apt install default-mysql-client php-mysql',
                    'rhel' => 'sudo dnf install mysql php-mysqlnd',
                    'docker' => 'apk add mysql-client php83-pdo_mysql',
                ],
            ],

            'pgsql' => [
                'id' => 'pgsql',
                'name' => 'PostgreSQL',
                'ready' => $pgsqlCli && $pgsqlExt,
                'php_driver' => [
                    'name' => 'pdo_pgsql',
                    'installed' => $pgsqlExt,
                ],
                'cli_tool' => [
                    'name' => 'pg_dump',
                    'installed' => $pgsqlCli,
                    'path' => $binaries['pg_dump']['path'],
                    'version' => $binaries['pg_dump']['version'],
                ],
                'install_hints' => [
                    'macos' => 'brew install libpq && brew link --force libpq',
                    'ubuntu' => 'sudo apt install postgresql-client php-pgsql',
                    'rhel' => 'sudo dnf install postgresql php-pgsql',
                    'docker' => 'apk add postgresql-client php83-pdo_pgsql',
                ],
            ],

            'mongodb' => [
                'id' => 'mongodb',
                'name' => 'MongoDB',
                'ready' => $mongoCli && $mongoExt,
                'php_driver' => [
                    'name' => 'sockets',
                    'installed' => $mongoExt,
                ],
                'cli_tool' => [
                    'name' => 'mongodump',
                    'installed' => $mongoCli,
                    'path' => $binaries['mongodump']['path'],
                    'version' => $binaries['mongodump']['version'],
                ],
                'install_hints' => [
                    'macos' => 'brew tap mongodb/brew && brew install mongodb-database-tools',
                    'ubuntu' => 'sudo apt install mongodb-database-tools',
                    'rhel' => 'sudo dnf install mongodb-database-tools',
                    'docker' => 'apk add mongodb-tools',
                ],
            ],

            'sqlite' => [
                'id' => 'sqlite',
                'name' => 'SQLite',
                'ready' => $sqliteExt,
                'php_driver' => [
                    'name' => 'pdo_sqlite',
                    'installed' => $sqliteExt,
                ],
                'cli_tool' => [
                    'name' => 'sqlite3',
                    'installed' => $sqliteCli,
                    'path' => $binaries['sqlite3']['path'],
                    'version' => $binaries['sqlite3']['version'],
                ],
                'install_hints' => [
                    'macos' => 'Pre-installed with macOS system utilities',
                    'ubuntu' => 'sudo apt install sqlite3 php-sqlite3',
                    'rhel' => 'sudo dnf install sqlite php-sqlite3',
                    'docker' => 'apk add sqlite php83-pdo_sqlite',
                ],
            ],
        ];
    }

    /**
     * Collect relevant server and environment details.
     *
     * @return array<string, mixed>
     */
    protected function getSystemInfo(): array
    {
        $tempDir = storage_path('app/temp-backups');
        File::ensureDirectoryExists($tempDir);

        $freeBytes = @disk_free_space($tempDir);
        $totalBytes = @disk_total_space($tempDir);

        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY.' ('.php_uname('s').' '.php_uname('r').')',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / PHP Development Server',
            'memory_limit' => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time' => ini_get('max_execution_time') ? ini_get('max_execution_time').'s' : 'Unlimited',
            'temp_directory' => $tempDir,
            'temp_directory_writable' => is_writable($tempDir),
            'free_disk_space_bytes' => $freeBytes ?: null,
            'total_disk_space_bytes' => $totalBytes ?: null,
        ];
    }

    /**
     * Run the binary with its version flag to retrieve version output.
     */
    protected function extractBinaryVersion(string $binaryPath, string $flag = '--version'): ?string
    {
        try {
            $process = new Process([$binaryPath, $flag]);
            $process->setTimeout(2);
            $process->run();

            $output = trim($process->getOutput() ?: $process->getErrorOutput());
            if ($output === '') {
                return null;
            }

            // Return the first line if multi-line output
            $lines = explode("\n", $output);

            return trim($lines[0]);
        } catch (\Throwable) {
            return null;
        }
    }
}
