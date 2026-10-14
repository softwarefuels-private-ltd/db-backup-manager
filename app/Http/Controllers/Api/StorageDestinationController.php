<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageDestination;
use App\Services\DynamicStorageFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class StorageDestinationController extends Controller
{
    /**
     * Display a listing of storage destinations.
     */
    public function index(): JsonResponse
    {
        $destinations = StorageDestination::withCount(['backupSchedules'])
            ->latest()
            ->get();

        return response()->json($destinations);
    }

    /**
     * Store a newly created storage destination.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeAzureFields($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'in:local,s3,sftp,azure'],
            'bucket' => ['nullable', 'string', 'required_if:driver,s3,azure'],
            'container' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'endpoint' => ['nullable', 'string'],
            'access_key_id' => ['nullable', 'string', 'required_if:driver,s3'],
            'secret_access_key' => ['nullable', 'string', 'required_if:driver,s3'],
            'host' => ['nullable', 'string', 'required_if:driver,sftp'],
            'port' => ['nullable', 'integer'],
            'username' => ['nullable', 'string', 'required_if:driver,sftp'],
            'password' => ['nullable', 'string'],
            'private_key' => ['nullable', 'string'],
            'root' => ['nullable', 'string'],
            'prefix' => ['nullable', 'string'],
            'connection_string' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string'],
            'account_key' => ['nullable', 'string'],
        ]);

        if ($validated['driver'] === 'azure') {
            if (empty($validated['connection_string']) && empty($validated['username'])) {
                throw ValidationException::withMessages([
                    'account_name' => 'Either Azure Storage Account Name or Connection String is required.',
                ]);
            }
        }

        // Clean redundant alias fields before inserting
        unset($validated['container'], $validated['prefix'], $validated['account_name'], $validated['account_key']);

        $destination = StorageDestination::create($validated);

        return response()->json([
            'message' => 'Storage destination created successfully',
            'destination' => $destination,
        ], 201);
    }

    /**
     * Display the specified destination.
     */
    public function show(StorageDestination $destination): JsonResponse
    {
        $destination->loadCount(['backupSchedules']);

        return response()->json($destination);
    }

    /**
     * Update the specified destination.
     */
    public function update(Request $request, StorageDestination $destination): JsonResponse
    {
        $this->normalizeAzureFields($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'driver' => ['sometimes', 'required', 'string', 'in:local,s3,sftp,azure'],
            'bucket' => ['nullable', 'string'],
            'container' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'endpoint' => ['nullable', 'string'],
            'access_key_id' => ['nullable', 'string'],
            'secret_access_key' => ['nullable', 'string'],
            'host' => ['nullable', 'string'],
            'port' => ['nullable', 'integer'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'private_key' => ['nullable', 'string'],
            'root' => ['nullable', 'string'],
            'prefix' => ['nullable', 'string'],
            'connection_string' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string'],
            'account_key' => ['nullable', 'string'],
        ]);

        // Don't overwrite secrets if passed empty
        foreach (['secret_access_key', 'password', 'private_key', 'connection_string'] as $secret) {
            if (array_key_exists($secret, $validated) && empty($validated[$secret])) {
                unset($validated[$secret]);
            }
        }

        unset($validated['container'], $validated['prefix'], $validated['account_name'], $validated['account_key']);

        $destination->update($validated);

        return response()->json([
            'message' => 'Storage destination updated successfully',
            'destination' => $destination,
        ]);
    }

    /**
     * Remove the specified destination.
     */
    public function destroy(StorageDestination $destination): JsonResponse
    {
        if ($destination->backupSchedules()->exists()) {
            return response()->json([
                'message' => 'Cannot delete storage destination because it is currently attached to one or more schedules.',
            ], 422);
        }

        $destination->delete();

        return response()->json([
            'message' => 'Storage destination deleted successfully',
        ]);
    }

    /**
     * Test write and delete access on target storage.
     */
    public function test(StorageDestination $destination): JsonResponse
    {
        try {
            $disk = DynamicStorageFactory::make($destination);
            $testFile = '.test-dbmanager-'.now()->timestamp.'.txt';

            $disk->put($testFile, 'Test write permission at '.now()->toIso8601String());

            if (! $disk->exists($testFile)) {
                throw new \Exception('File was written but could not be verified on storage.');
            }

            $disk->delete($testFile);

            return response()->json([
                'success' => true,
                'message' => "Successfully connected to [{$destination->name}] ({$destination->driver}) and verified read/write permissions!",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Storage test failed: {$e->getMessage()}",
            ], 422);
        }
    }

    /**
     * Normalize Azure alias input fields into canonical model attributes.
     */
    protected function normalizeAzureFields(Request $request): void
    {
        if ($request->input('driver') === 'azure') {
            if ($request->filled('container') && ! $request->filled('bucket')) {
                $request->merge(['bucket' => $request->input('container')]);
            }
            if ($request->filled('prefix') && ! $request->filled('root')) {
                $request->merge(['root' => $request->input('prefix')]);
            }
            if ($request->filled('account_name') && ! $request->filled('username')) {
                $request->merge(['username' => $request->input('account_name')]);
            }
            if ($request->filled('account_key') && ! $request->filled('password')) {
                $request->merge(['password' => $request->input('account_key')]);
            }
        }
    }
}
