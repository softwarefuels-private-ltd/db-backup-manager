<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DatabaseConnection;
use App\Services\DatabaseHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseConnectionController extends Controller
{
    public function __construct(
        protected DatabaseHealthService $healthService
    ) {}

    /**
     * Display a listing of database connections.
     */
    public function index(): JsonResponse
    {
        $connections = DatabaseConnection::withCount(['backupSchedules', 'backupRuns'])
            ->latest()
            ->get();

        return response()->json($connections);
    }

    /**
     * Store a newly created database connection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'in:mysql,mariadb,pgsql,sqlite,mongodb'],
            'host' => ['nullable', 'string', 'required_if:driver,mysql,mariadb,pgsql,mongodb'],
            'port' => ['nullable', 'integer'],
            'database' => ['nullable', 'string', 'required_if:driver,mysql,mariadb,pgsql'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'database_path' => ['nullable', 'string', 'required_if:driver,sqlite'],
            'extra_options' => ['nullable', 'array'],
        ]);

        $connection = DatabaseConnection::create($validated);

        return response()->json([
            'message' => 'Database connection created successfully',
            'connection' => $connection,
        ], 201);
    }

    /**
     * Display the specified connection.
     */
    public function show(DatabaseConnection $connection): JsonResponse
    {
        $connection->loadCount(['backupSchedules', 'backupRuns']);

        return response()->json($connection);
    }

    /**
     * Update the specified connection.
     */
    public function update(Request $request, DatabaseConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'driver' => ['sometimes', 'required', 'string', 'in:mysql,mariadb,pgsql,sqlite,mongodb'],
            'host' => ['nullable', 'string'],
            'port' => ['nullable', 'integer'],
            'database' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'database_path' => ['nullable', 'string'],
            'extra_options' => ['nullable', 'array'],
        ]);

        // Don't overwrite existing password if left empty on update
        if (array_key_exists('password', $validated) && empty($validated['password'])) {
            unset($validated['password']);
        }

        $connection->update($validated);
        $this->healthService->forgetCache($connection->id);

        return response()->json([
            'message' => 'Database connection updated successfully',
            'connection' => $connection,
        ]);
    }

    /**
     * Remove the specified connection.
     */
    public function destroy(DatabaseConnection $connection): JsonResponse
    {
        $this->healthService->forgetCache($connection->id);
        $connection->delete();

        return response()->json([
            'message' => 'Database connection deleted successfully',
        ]);
    }

    /**
     * Test the connectivity to the target database.
     */
    public function test(DatabaseConnection $connection): JsonResponse
    {
        $health = $this->healthService->check($connection, force: true);

        if ($health['healthy']) {
            return response()->json([
                'success' => true,
                'message' => $health['message'],
                'health' => $health,
                'latency_ms' => $health['latency_ms'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Connection test failed: {$health['message']}",
            'health' => $health,
        ], 422);
    }

    /**
     * Check health and latency for all database connections.
     */
    public function healthCheckAll(): JsonResponse
    {
        $connections = DatabaseConnection::withCount(['backupSchedules', 'backupRuns'])
            ->with(['backupRuns' => function ($query) {
                $query->latest()->take(1);
            }])
            ->latest()
            ->get()
            ->map(function (DatabaseConnection $connection): array {
                $health = $this->healthService->check($connection, force: true);
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

        return response()->json($connections);
    }
}
