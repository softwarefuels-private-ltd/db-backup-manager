<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'database_connection_id',
    'storage_destination_id',
    'name',
    'cron_expression',
    'timezone',
    'enabled',
    'retention_days',
    'keep_last_count',
    'last_run_at',
    'next_run_at',
])]
class BackupSchedule extends Model
{
    /**
     * Get the database connection for the schedule.
     *
     * @return BelongsTo<DatabaseConnection, $this>
     */
    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    /**
     * Get the storage destination for the schedule.
     *
     * @return BelongsTo<StorageDestination, $this>
     */
    public function storageDestination(): BelongsTo
    {
        return $this->belongsTo(StorageDestination::class);
    }

    /**
     * Get the runs for the schedule.
     *
     * @return HasMany<BackupRun, $this>
     */
    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'retention_days' => 'integer',
            'keep_last_count' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
