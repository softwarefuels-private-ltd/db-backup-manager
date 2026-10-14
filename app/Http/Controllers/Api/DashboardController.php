<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\BackupSchedule;
use App\Models\DatabaseConnection;
use App\Models\StorageDestination;
use App\Services\DatabaseHealthService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DatabaseHealthService $healthService
    ) {}

    /**
     * Get aggregate statistics and recent activities for the dashboard.
     */
    public function stats(): JsonResponse
    {
        $totalConnections = DatabaseConnection::count();
        $totalDestinations = StorageDestination::count();
        $totalSchedules = BackupSchedule::count();
        $activeSchedules = BackupSchedule::where('enabled', true)->count();

        $totalRuns = BackupRun::count();
        $completedRuns = BackupRun::where('status', 'completed')->count();
        $failedRuns = BackupRun::where('status', 'failed')->count();
        $runningRuns = BackupRun::where('status', 'running')->count();

        $totalStorageBytes = (int) BackupRun::where('status', 'completed')->sum('size');

        $databaseConnections = DatabaseConnection::withCount(['backupSchedules', 'backupRuns'])
            ->with(['backupRuns' => function ($query) {
                $query->latest()->take(1);
            }])
            ->latest()
            ->get()
            ->map(function (DatabaseConnection $connection): array {
                $health = $this->healthService->check($connection);
                $lastRun = $connection->backupRuns->first();

                return [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'driver' => $connection->driver,
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'database' => $connection->database,
                    'database_path' => $connection->database_path,
                    'username' => $connection->username,
                    'backup_schedules_count' => $connection->backup_schedules_count,
                    'backup_runs_count' => $connection->backup_runs_count,
                    'last_backup_run' => $lastRun ? [
                        'id' => $lastRun->id,
                        'status' => $lastRun->status,
                        'size' => $lastRun->size,
                        'created_at' => $lastRun->created_at,
                    ] : null,
                    'health' => $health,
                ];
            });

        $recentRuns = BackupRun::with(['databaseConnection:id,name,driver', 'storageDestination:id,name,driver', 'backupSchedule:id,name'])
            ->latest()
            ->take(8)
            ->get();

        $upcomingSchedules = BackupSchedule::with(['databaseConnection:id,name,driver', 'storageDestination:id,name,driver'])
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_connections' => $totalConnections,
                'total_destinations' => $totalDestinations,
                'total_schedules' => $totalSchedules,
                'active_schedules' => $activeSchedules,
                'total_runs' => $totalRuns,
                'completed_runs' => $completedRuns,
                'failed_runs' => $failedRuns,
                'running_runs' => $runningRuns,
                'total_storage_bytes' => $totalStorageBytes,
            ],
            'database_connections' => $databaseConnections,
            'recent_runs' => $recentRuns,
            'upcoming_schedules' => $upcomingSchedules,
        ]);
    }
}
