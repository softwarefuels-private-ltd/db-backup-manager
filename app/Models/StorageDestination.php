<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'driver',
    'bucket',
    'region',
    'endpoint',
    'access_key_id',
    'secret_access_key',
    'host',
    'port',
    'username',
    'password',
    'private_key',
    'root',
    'connection_string',
])]
class StorageDestination extends Model
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'container',
        'prefix',
        'account_name',
    ];

    /**
     * Get the backup schedules that use this destination.
     *
     * @return HasMany<BackupSchedule, $this>
     */
    public function backupSchedules(): HasMany
    {
        return $this->hasMany(BackupSchedule::class);
    }

    /**
     * Alias container to bucket.
     */
    public function getContainerAttribute(): ?string
    {
        return $this->bucket;
    }

    /**
     * Set container attribute to bucket.
     */
    public function setContainerAttribute(?string $value): void
    {
        $this->attributes['bucket'] = $value;
    }

    /**
     * Alias prefix to root.
     */
    public function getPrefixAttribute(): ?string
    {
        return $this->root;
    }

    /**
     * Set prefix attribute to root.
     */
    public function setPrefixAttribute(?string $value): void
    {
        $this->attributes['root'] = $value;
    }

    /**
     * Alias account_name to username.
     */
    public function getAccountNameAttribute(): ?string
    {
        return $this->username ?: $this->access_key_id;
    }

    /**
     * Set account_name attribute to username.
     */
    public function setAccountNameAttribute(?string $value): void
    {
        $this->attributes['username'] = $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_access_key' => 'encrypted',
            'password' => 'encrypted',
            'private_key' => 'encrypted',
            'connection_string' => 'encrypted',
        ];
    }
}
