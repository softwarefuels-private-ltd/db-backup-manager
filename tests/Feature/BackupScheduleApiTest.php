<?php

namespace Tests\Feature;

use App\Jobs\ProcessBackupJob;
use App\Models\BackupSchedule;
use App\Models\DatabaseConnection;
use App\Models\StorageDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected DatabaseConnection $connection;

    protected StorageDestination $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->connection = DatabaseConnection::create([
            'name' => 'Primary MySQL',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'app_db',
            'username' => 'root',
        ]);

        $this->destination = StorageDestination::create([
            'name' => 'Local Backups',
            'driver' => 'local',
            'root' => '/backups',
        ]);
    }

    public function test_can_create_backup_schedule(): void
    {
        $payload = [
            'database_connection_id' => $this->connection->id,
            'storage_destination_id' => $this->destination->id,
            'name' => 'Nightly Backup',
            'cron_expression' => '0 2 * * *',
            'timezone' => 'UTC',
            'enabled' => true,
            'keep_last_count' => 14,
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nightly Backup']);

        $this->assertDatabaseHas('backup_schedules', [
            'name' => 'Nightly Backup',
            'keep_last_count' => 14,
        ]);
    }

    public function test_validates_invalid_cron_expression(): void
    {
        $payload = [
            'database_connection_id' => $this->connection->id,
            'storage_destination_id' => $this->destination->id,
            'name' => 'Bad Cron',
            'cron_expression' => 'invalid-cron-timing',
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cron_expression']);
    }

    public function test_can_manually_trigger_backup_schedule(): void
    {
        Queue::fake();

        $schedule = BackupSchedule::create([
            'database_connection_id' => $this->connection->id,
            'storage_destination_id' => $this->destination->id,
            'name' => 'Hourly Sync',
            'cron_expression' => '0 * * * *',
        ]);

        $response = $this->postJson("/api/v1/schedules/{$schedule->id}/trigger");

        $response->assertStatus(202)
            ->assertJsonFragment(['trigger_type' => 'manual', 'status' => 'pending']);

        $this->assertDatabaseHas('backup_runs', [
            'backup_schedule_id' => $schedule->id,
            'trigger_type' => 'manual',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessBackupJob::class);
    }
}
