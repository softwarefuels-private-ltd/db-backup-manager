<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBackupJob;
use App\Models\BackupRun;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BackupScheduleController extends Controller
{
    /**
     * Display a listing of backup schedules.
     */
    public function index(): JsonResponse
    {
        $schedules = BackupSchedule::with(['databaseConnection:id,name,driver,database', 'storageDestination:id,name,driver'])
            ->withCount(['backupRuns'])
            ->latest()
            ->get();

        return response()->json($schedules);
    }

    /**
     * Store a newly created schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'database_connection_id' => ['required', 'exists:database_connections,id'],
            'storage_destination_id' => ['required', 'exists:storage_destinations,id'],
            'name' => ['required', 'string', 'max:255'],
            'cron_expression' => ['required', 'string'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'enabled' => ['boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1'],
            'keep_last_count' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! CronExpression::isValidExpression($validated['cron_expression'])) {
            throw ValidationException::withMessages([
                'cron_expression' => ['The specified cron expression is invalid.'],
            ]);
        }

        $timezone = $validated['timezone'] ?? 'UTC';
        $cron = new CronExpression($validated['cron_expression']);
        $validated['next_run_at'] = $cron->getNextRunDate(now()->toDateTimeString(), 0, false, $timezone);

        $schedule = BackupSchedule::create($validated);
        $schedule->load(['databaseConnection', 'storageDestination']);

        return response()->json([
            'message' => 'Backup schedule created successfully',
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Display the specified schedule.
     */
    public function show(BackupSchedule $schedule): JsonResponse
    {
        $schedule->load(['databaseConnection', 'storageDestination', 'backupRuns' => function ($q) {
            $q->latest()->take(10);
        }]);

        return response()->json($schedule);
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, BackupSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'database_connection_id' => ['sometimes', 'required', 'exists:database_connections,id'],
            'storage_destination_id' => ['sometimes', 'required', 'exists:storage_destinations,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'cron_expression' => ['sometimes', 'required', 'string'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'enabled' => ['boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1'],
            'keep_last_count' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['cron_expression'])) {
            if (! CronExpression::isValidExpression($validated['cron_expression'])) {
                throw ValidationException::withMessages([
                    'cron_expression' => ['The specified cron expression is invalid.'],
                ]);
            }
            $timezone = $validated['timezone'] ?? $schedule->timezone ?? 'UTC';
            $cron = new CronExpression($validated['cron_expression']);
            $validated['next_run_at'] = $cron->getNextRunDate(now()->toDateTimeString(), 0, false, $timezone);
        }

        $schedule->update($validated);
        $schedule->load(['databaseConnection', 'storageDestination']);

        return response()->json([
            'message' => 'Backup schedule updated successfully',
            'schedule' => $schedule,
        ]);
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(BackupSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Backup schedule deleted successfully',
        ]);
    }

    /**
     * Manually trigger an immediate backup for this schedule.
     */
    public function trigger(BackupSchedule $schedule): JsonResponse
    {
        $run = BackupRun::create([
            'backup_schedule_id' => $schedule->id,
            'database_connection_id' => $schedule->database_connection_id,
            'storage_destination_id' => $schedule->storage_destination_id,
            'trigger_type' => 'manual',
            'status' => 'pending',
        ]);

        ProcessBackupJob::dispatch($run);

        return response()->json([
            'message' => 'Backup triggered successfully',
            'run' => $run,
        ], 202);
    }
}
