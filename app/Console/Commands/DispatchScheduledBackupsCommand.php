<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBackupJob;
use App\Models\BackupRun;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DispatchScheduledBackupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:dispatch {--force : Dispatch all enabled schedules immediately regardless of cron timing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check enabled backup schedules and dispatch due backup jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $schedules = BackupSchedule::where('enabled', true)
            ->with(['databaseConnection', 'storageDestination'])
            ->get();

        $dispatched = 0;

        foreach ($schedules as $schedule) {
            try {
                $timezone = $schedule->timezone ?: 'UTC';
                $now = now()->setTimezone(new DateTimeZone($timezone));

                $isDue = false;

                if ($force) {
                    $isDue = true;
                } elseif (CronExpression::isValidExpression($schedule->cron_expression)) {
                    $cron = new CronExpression($schedule->cron_expression);
                    $isDue = $cron->isDue($now->toDateTimeString(), $timezone);
                }

                if ($isDue) {
                    if (! $force) {
                        // Concurrency lock: prevent another scheduler worker from dispatching the same schedule at the same time
                        $lockKey = "backup_schedule_dispatch_{$schedule->id}_".$now->format('YmdHi');
                        try {
                            $lock = Cache::lock($lockKey, 55);
                            if (! $lock->get()) {
                                $this->line("Schedule [{$schedule->name}] is already being processed by another scheduler worker. Skipping.");

                                continue;
                            }
                        } catch (Throwable) {
                            // If the cache driver does not support atomic locks, continue to database check
                        }

                        // Database deduplication: prevent dispatching more than once in the same minute
                        $alreadyDispatched = BackupRun::where('backup_schedule_id', $schedule->id)
                            ->where('created_at', '>=', now()->startOfMinute())
                            ->exists();

                        if ($alreadyDispatched) {
                            $this->line("Schedule [{$schedule->name}] was already dispatched in this minute. Skipping.");

                            continue;
                        }

                        // Overlap protection: skip if an existing backup run for this schedule is still in progress
                        $hasActiveRun = BackupRun::where('backup_schedule_id', $schedule->id)
                            ->whereIn('status', ['pending', 'running'])
                            ->where('created_at', '>=', now()->subHours(2))
                            ->exists();

                        if ($hasActiveRun) {
                            $this->line("Schedule [{$schedule->name}] already has a backup run in progress. Skipping.");

                            continue;
                        }
                    }

                    $this->info("Dispatching backup for schedule: [{$schedule->name}] (DB: {$schedule->databaseConnection?->name})");

                    $run = BackupRun::create([
                        'backup_schedule_id' => $schedule->id,
                        'database_connection_id' => $schedule->database_connection_id,
                        'storage_destination_id' => $schedule->storage_destination_id,
                        'trigger_type' => $force ? 'manual' : 'scheduled',
                        'status' => 'pending',
                    ]);

                    ProcessBackupJob::dispatch($run);
                    $dispatched++;

                    if (CronExpression::isValidExpression($schedule->cron_expression)) {
                        $cron = new CronExpression($schedule->cron_expression);
                        $schedule->update([
                            'next_run_at' => $cron->getNextRunDate($now->toDateTimeString(), 0, false, $timezone),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $this->error("Error checking schedule [{$schedule->id}]: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$schedules->count()} schedules. Dispatched {$dispatched} backup jobs.");

        return Command::SUCCESS;
    }
}
