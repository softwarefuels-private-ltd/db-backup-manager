<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBackupJob;
use App\Models\BackupRun;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Command;
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
