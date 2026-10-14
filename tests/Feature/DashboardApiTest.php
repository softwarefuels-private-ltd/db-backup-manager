<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_stats_endpoint_includes_database_connections_with_health(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_db_').'.sqlite';
        touch($tempPath);

        DatabaseConnection::create([
            'name' => 'Dashboard SQLite',
            'driver' => 'sqlite',
            'database_path' => $tempPath,
        ]);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'total_connections',
                    'total_destinations',
                    'total_schedules',
                    'active_schedules',
                    'total_runs',
                    'completed_runs',
                    'failed_runs',
                    'running_runs',
                    'total_storage_bytes',
                ],
                'database_connections' => [
                    '*' => [
                        'id',
                        'name',
                        'driver',
                        'health' => [
                            'status',
                            'healthy',
                            'latency_ms',
                            'message',
                            'checked_at',
                        ],
                    ],
                ],
                'recent_runs',
                'upcoming_schedules',
            ])
            ->assertJsonPath('database_connections.0.health.healthy', true);

        @unlink($tempPath);
    }
}
