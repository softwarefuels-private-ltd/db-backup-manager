<?php

namespace Tests\Feature;

use App\Jobs\ProcessBackupJob;
use App\Models\BackupRun;
use App\Models\BackupSchedule;
use App\Models\DatabaseConnection;
use App\Models\StorageDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchScheduledBackupsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected DatabaseConnection $connection;

    protected StorageDestination $destination;

    protected BackupSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->connection = DatabaseConnection::create([
            'name' => 'Test MySQL',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test_db',
            'username' => 'root',
        ]);

        $this->destination = StorageDestination::create([
            'name' => 'Local Disk',
            'driver' => 'local',
            'root' => '/backups',
        ]);

        // Scheduled to run every minute
        $this->schedule = BackupSchedule::create([
            'database_connection_id' => $this->connection->id,
            'storage_destination_id' => $this->destination->id,
            'name' => 'Every Minute Backup',
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'enabled' => true,
        ]);
    }

    public function test_due_schedule_is_dispatched(): void
    {
        $this->artisan('backup:dispatch')
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 1);
        Queue::assertPushed(ProcessBackupJob::class, 1);
    }

    public function test_duplicate_dispatch_in_same_minute_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:10'));

        // First dispatch (e.g. from Service 1)
        $this->artisan('backup:dispatch')
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 1);
        Queue::assertPushed(ProcessBackupJob::class, 1);

        // Second dispatch in the same minute (e.g. from Service 2 at 02:00:12)
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:12'));

        $this->artisan('backup:dispatch')
            ->assertSuccessful();

        // Must still only be 1 run
        $this->assertDatabaseCount('backup_runs', 1);
        Queue::assertPushed(ProcessBackupJob::class, 1);
    }

    public function test_active_run_in_progress_prevents_duplicate_dispatch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:00'));

        BackupRun::create([
            'backup_schedule_id' => $this->schedule->id,
            'database_connection_id' => $this->connection->id,
            'storage_destination_id' => $this->destination->id,
            'trigger_type' => 'scheduled',
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Move to the next minute
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:01:00'));

        $this->artisan('backup:dispatch')
            ->expectsOutputToContain('already has a backup run in progress')
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 1);
        Queue::assertNothingPushed();
    }

    public function test_concurrency_lock_prevents_simultaneous_dispatch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:00'));

        $lockKey = "backup_schedule_dispatch_{$this->schedule->id}_202610160200";
        $lock = Cache::lock($lockKey, 55);
        $lock->get();

        $this->artisan('backup:dispatch')
            ->expectsOutputToContain('is already being processed by another scheduler worker')
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_force_flag_bypasses_deduplication(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:10'));

        $this->artisan('backup:dispatch')
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 1);

        // Force run in the same minute
        $this->artisan('backup:dispatch', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('backup_runs', 2);
        Queue::assertPushed(ProcessBackupJob::class, 2);
    }
}
