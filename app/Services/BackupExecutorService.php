<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use Exception;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Str;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Throwable;

class BackupExecutorService
{
    /**
     * Execute a backup run.
     */
    public function execute(BackupRun $run): BackupRun
    {
        $startTime = microtime(true);
        $tempDir = storage_path('app/temp-backups');
        FileFacade::ensureDirectoryExists($tempDir);

        $connection = $run->databaseConnection ?? $run->backupSchedule?->databaseConnection;
        $destination = $run->storageDestination ?? $run->backupSchedule?->storageDestination;

        if (! $connection) {
            $run->update([
                'status' => 'failed',
                'error_message' => 'No database connection configured for this backup run.',
                'completed_at' => now(),
            ]);

            return $run;
        }

        if (! $destination) {
            $run->update([
                'status' => 'failed',
                'error_message' => 'No storage destination configured for this backup run.',
                'completed_at' => now(),
            ]);

            return $run;
        }

        $run->update([
            'database_connection_id' => $connection->id,
            'storage_destination_id' => $destination->id,
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $dbNameSlug = Str::slug($connection->database ?: $connection->name, '_');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $driver = strtolower($connection->driver);

        $filename = "{$dbNameSlug}_{$driver}_{$timestamp}.sql.gz";
        if (in_array($driver, ['mongodb', 'mongo'])) {
            $filename = "{$dbNameSlug}_mongo_{$timestamp}.archive.gz";
        }

        $tempFilePath = "{$tempDir}/{$filename}";

        try {
            $dumper = BackupDumperFactory::make($connection);

            if (in_array($driver, ['mongodb', 'mongo'])) {
                // mongodump native gzip archive
                $dumper->addExtraOption("--archive={$tempFilePath}");
                $dumper->addExtraOption('--gzip');
                // Spatie MongoDb dumper dumpToFile expects destination directory or runs binary
                $dumper->dumpToFile($tempDir);
            } else {
                $dumper->useCompressor(new GzipCompressor);
                $dumper->dumpToFile($tempFilePath);
            }

            if (! file_exists($tempFilePath)) {
                throw new Exception("Dump file was not generated at: {$tempFilePath}");
            }

            $fileSize = filesize($tempFilePath);

            // Connect to target storage disk and stream file
            $disk = DynamicStorageFactory::make($destination);
            $targetPath = $filename;

            $disk->putFileAs('', new File($tempFilePath), $targetPath);

            $duration = (int) round(microtime(true) - $startTime);

            $run->update([
                'status' => 'completed',
                'filename' => $filename,
                'path' => $targetPath,
                'size' => $fileSize,
                'duration_seconds' => $duration,
                'completed_at' => now(),
                'output_log' => "Successfully backed up [{$connection->name}] ({$driver}) to [{$destination->name}] ({$destination->driver}). Size: {$fileSize} bytes.",
            ]);

            // Enforce retention & schedule stats if run came from a schedule
            if ($run->backupSchedule) {
                $this->applyRetention($run->backupSchedule, $disk);
                $this->updateScheduleTimings($run->backupSchedule);
            }

        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'output_log' => $e->getTraceAsString(),
            ]);
        } finally {
            if (file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }

        return $run;
    }

    /**
     * Apply retention policies (by count and/or age).
     */
    protected function applyRetention(BackupSchedule $schedule, $disk): void
    {
        $query = $schedule->backupRuns()
            ->where('status', 'completed')
            ->orderByDesc('created_at');

        // Retention by Count (keep latest N)
        if ($schedule->keep_last_count && $schedule->keep_last_count > 0) {
            $toPrune = (clone $query)->skip($schedule->keep_last_count)->take(PHP_INT_MAX)->get();
            foreach ($toPrune as $oldRun) {
                if ($oldRun->path && $disk->exists($oldRun->path)) {
                    $disk->delete($oldRun->path);
                }
                $oldRun->delete();
            }
        }

        // Retention by Days (keep backups younger than N days)
        if ($schedule->retention_days && $schedule->retention_days > 0) {
            $cutoff = now()->subDays($schedule->retention_days);
            $expiredRuns = $schedule->backupRuns()
                ->where('status', 'completed')
                ->where('created_at', '<', $cutoff)
                ->get();

            foreach ($expiredRuns as $expired) {
                if ($expired->path && $disk->exists($expired->path)) {
                    $disk->delete($expired->path);
                }
                $expired->delete();
            }
        }
    }

    /**
     * Compute next run timing using CronExpression.
     */
    public function updateScheduleTimings(BackupSchedule $schedule): void
    {
        $now = now();
        $cron = new CronExpression($schedule->cron_expression);
        $next = $cron->getNextRunDate($now->toDateTimeString(), 0, false, $schedule->timezone ?: 'UTC');

        $schedule->update([
            'last_run_at' => $now,
            'next_run_at' => $next,
        ]);
    }
}
