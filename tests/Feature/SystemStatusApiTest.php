<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_retrieve_system_status_and_driver_requirements(): void
    {
        $response = $this->getJson('/api/v1/system/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_engines',
                    'ready_engines',
                    'all_ready',
                ],
                'engines' => [
                    'mysql' => [
                        'id',
                        'name',
                        'ready',
                        'php_driver',
                        'cli_tool',
                        'install_hints',
                    ],
                    'pgsql',
                    'mongodb',
                    'sqlite',
                ],
                'binaries' => [
                    'mysqldump',
                    'mariadb-dump',
                    'pg_dump',
                    'mongodump',
                    'sqlite3',
                    'gzip',
                ],
                'php_extensions' => [
                    'pdo_mysql',
                    'pdo_pgsql',
                    'pdo_sqlite',
                    'sockets',
                    'curl',
                    'zlib',
                    'openssl',
                ],
                'system_info' => [
                    'php_version',
                    'os',
                    'memory_limit',
                    'temp_directory',
                    'temp_directory_writable',
                ],
            ]);
    }
}
