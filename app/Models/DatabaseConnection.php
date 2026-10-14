<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'driver',
    'host',
    'port',
    'database',
    'username',
    'password',
    'database_path',
    'extra_options',
])]
class DatabaseConnection extends Model
{
    /**
     * Get the backup schedules that use this connection.
     *
     * @return HasMany<BackupSchedule, $this>
     */
    public function backupSchedules(): HasMany
    {
        return $this->hasMany(BackupSchedule::class);
    }

    /**
     * Get the direct backup runs for this connection.
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
            'password' => 'encrypted',
            'extra_options' => 'encrypted:array',
            'port' => 'integer',
        ];
    }
}
