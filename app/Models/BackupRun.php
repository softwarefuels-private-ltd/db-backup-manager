<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'backup_schedule_id',
    'database_connection_id',
    'storage_destination_id',
    'trigger_type',
    'status',
    'filename',
    'path',
    'size',
    'duration_seconds',
    'started_at',
    'completed_at',
    'error_message',
    'output_log',
])]
class BackupRun extends Model
{
    /**
     * Get the schedule that produced the run.
     *
     * @return BelongsTo<BackupSchedule, $this>
     */
    public function backupSchedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class);
    }

    /**
     * Get the database connection for this run.
     *
     * @return BelongsTo<DatabaseConnection, $this>
     */
    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    /**
     * Get the storage destination for this run.
     *
     * @return BelongsTo<StorageDestination, $this>
     */
    public function storageDestination(): BelongsTo
    {
        return $this->belongsTo(StorageDestination::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }
}
