<?php

namespace App\Services;

use App\Models\StorageDestination;
use App\Services\Storage\AzureBlobStorageAdapter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\Filesystem as Flysystem;

class DynamicStorageFactory
{
    /**
     * Build an on-the-fly Flysystem disk from a StorageDestination model.
     */
    public static function make(StorageDestination $dest): Filesystem
    {
        return match (strtolower($dest->driver)) {
            'local' => Storage::build([
                'driver' => 'local',
                'root' => $dest->root ?: storage_path('app/backups'),
            ]),

            's3' => Storage::build([
                'driver' => 's3',
                'key' => $dest->access_key_id,
                'secret' => $dest->secret_access_key,
                'region' => $dest->region ?: 'us-east-1',
                'bucket' => $dest->bucket,
                'endpoint' => $dest->endpoint,
                'use_path_style_endpoint' => ! empty($dest->endpoint),
                'root' => $dest->root ?: 'backups',
            ]),

            'sftp' => Storage::build([
                'driver' => 'sftp',
                'host' => $dest->host,
                'port' => $dest->port ?: 22,
                'username' => $dest->username,
                'password' => $dest->password,
                'privateKey' => $dest->private_key,
                'root' => $dest->root ?: '/backups',
                'timeout' => 30,
            ]),

            'azure', 'azure-blob' => static::makeAzureDisk($dest),

            default => throw new InvalidArgumentException("Unsupported storage driver: [{$dest->driver}]"),
        };
    }

    /**
     * Build an on-the-fly Azure Blob Storage disk.
     */
    protected static function makeAzureDisk(StorageDestination $dest): Filesystem
    {
        $accountName = $dest->account_name ?: $dest->username;
        $accountKey = $dest->account_key ?: $dest->password;
        $container = $dest->container ?: $dest->bucket ?: '';
        $prefix = $dest->prefix ?: $dest->root ?: '';

        $adapter = new AzureBlobStorageAdapter(
            accountName: $accountName,
            accountKey: $accountKey,
            container: $container,
            prefix: $prefix,
            endpoint: $dest->endpoint,
            connectionString: $dest->connection_string
        );

        $flysystem = new Flysystem($adapter);

        return new LaravelFilesystemAdapter($flysystem, $adapter);
    }
}
