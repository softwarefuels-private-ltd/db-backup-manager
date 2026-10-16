<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_database_connections(): void
    {
        DatabaseConnection::create([
            'name' => 'Main MySQL',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'production_db',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $response = $this->getJson('/api/v1/connections');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Main MySQL']);
    }

    public function test_can_create_database_connection(): void
    {
        $payload = [
            'name' => 'Analytics Postgres',
            'driver' => 'pgsql',
            'host' => '10.0.0.5',
            'port' => 5432,
            'database' => 'analytics',
            'username' => 'analyst',
            'password' => 'securepass',
        ];

        $response = $this->postJson('/api/v1/connections', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Analytics Postgres']);

        $this->assertDatabaseHas('database_connections', ['name' => 'Analytics Postgres']);
    }

    public function test_can_delete_database_connection(): void
    {
        $conn = DatabaseConnection::create([
            'name' => 'To Delete',
            'driver' => 'sqlite',
            'database_path' => '/tmp/test.db',
        ]);

        $response = $this->deleteJson("/api/v1/connections/{$conn->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('database_connections', ['id' => $conn->id]);
    }

    public function test_can_test_healthy_sqlite_connection(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_db_').'.sqlite';
        touch($tempPath);

        $conn = DatabaseConnection::create([
            'name' => 'Valid SQLite',
            'driver' => 'sqlite',
            'database_path' => $tempPath,
        ]);

        $response = $this->postJson("/api/v1/connections/{$conn->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('health.healthy', true)
            ->assertJsonStructure(['success', 'message', 'latency_ms', 'health']);

        @unlink($tempPath);
    }

    public function test_fails_for_nonexistent_sqlite_file(): void
    {
        $conn = DatabaseConnection::create([
            'name' => 'Missing SQLite',
            'driver' => 'sqlite',
            'database_path' => '/path/to/nonexistent/db.sqlite',
        ]);

        $response = $this->postJson("/api/v1/connections/{$conn->id}/test");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('health.healthy', false);
    }

    public function test_can_check_all_connections_health(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_db_').'.sqlite';
        touch($tempPath);

        DatabaseConnection::create([
            'name' => 'Valid SQLite All',
            'driver' => 'sqlite',
            'database_path' => $tempPath,
        ]);

        $response = $this->postJson('/api/v1/connections/health-check');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.health.healthy', true);

        @unlink($tempPath);
    }

    public function test_credentials_are_encrypted_in_db_auto_decrypted_in_code_and_hidden_from_json(): void
    {
        $plainSecret = 'super-secret-password-xyz';

        $conn = DatabaseConnection::create([
            'name' => 'Secure MySQL',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'secure_db',
            'username' => 'admin',
            'password' => $plainSecret,
        ]);

        // 1. Raw DB value is encrypted and not stored in plain text
        $raw = DB::table('database_connections')->where('id', $conn->id)->first();
        $this->assertNotEquals($plainSecret, $raw->password);
        $this->assertStringNotContainsString($plainSecret, $raw->password);

        // 2. Eloquent model auto-decrypts attribute when accessed by code
        $fresh = DatabaseConnection::find($conn->id);
        $this->assertEquals($plainSecret, $fresh->password);

        // 3. Hidden from serialization and API responses
        $this->assertArrayNotHasKey('password', $fresh->toArray());
        $response = $this->getJson("/api/v1/connections/{$conn->id}");
        $response->assertStatus(200)
            ->assertJsonMissing(['password' => $plainSecret])
            ->assertJsonMissingPath('password');
    }
}
