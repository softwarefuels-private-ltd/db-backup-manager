<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\DynamicStorageFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupRunController extends Controller
{
    /**
     * Display a paginated listing of backup runs with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BackupRun::with([
            'databaseConnection:id,name,driver,database',
            'storageDestination:id,name,driver',
            'backupSchedule:id,name',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('database_connection_id')) {
            $query->where('database_connection_id', $request->query('database_connection_id'));
        }

        if ($request->filled('backup_schedule_id')) {
            $query->where('backup_schedule_id', $request->query('backup_schedule_id'));
        }

        $runs = $query->paginate($request->query('per_page', 15));

        return response()->json($runs);
    }

    /**
     * Display details for a single backup run.
     */
    public function show(BackupRun $run): JsonResponse
    {
        $run->load([
            'databaseConnection',
            'storageDestination',
            'backupSchedule',
        ]);

        return response()->json($run);
    }

    /**
     * Download the backup file from storage.
     */
    public function download(BackupRun $run): StreamedResponse|JsonResponse
    {
        if ($run->status !== 'completed' || empty($run->path)) {
            return response()->json([
                'message' => 'This backup is not completed or has no associated file.',
            ], 404);
        }

        $destination = $run->storageDestination ?? $run->backupSchedule?->storageDestination;

        if (! $destination) {
            return response()->json([
                'message' => 'Associated storage destination not found.',
            ], 404);
        }

        try {
            $disk = DynamicStorageFactory::make($destination);

            if (! $disk->exists($run->path)) {
                return response()->json([
                    'message' => 'Backup file does not exist on storage destination.',
                ], 404);
            }

            return response()->streamDownload(function () use ($disk, $run) {
                $stream = $disk->readStream($run->path);
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, $run->filename ?: basename($run->path), [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to download backup: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified backup run and delete its remote file.
     */
    public function destroy(BackupRun $run): JsonResponse
    {
        try {
            $destination = $run->storageDestination ?? $run->backupSchedule?->storageDestination;
            if ($destination && $run->path) {
                $disk = DynamicStorageFactory::make($destination);
                if ($disk->exists($run->path)) {
                    $disk->delete($run->path);
                }
            }
        } catch (\Throwable) {
            // Ignore storage deletion errors and continue deleting DB record
        }

        $run->delete();

        return response()->json([
            'message' => 'Backup run deleted successfully',
        ]);
    }
}
