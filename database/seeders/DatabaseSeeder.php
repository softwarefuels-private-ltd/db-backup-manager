<?php

namespace Database\Seeders;

use App\Models\BackupSchedule;
use App\Models\DatabaseConnection;
use App\Models\StorageDestination;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initial Administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@synkcode.com'],
            [
                'name' => 'Internal Admin',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Default Local Storage Disk
        $localStorage = StorageDestination::firstOrCreate(
            ['name' => 'Default Local Backups'],
            [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
            ]
        );

        // 3. Example Self SQLite Connection
        $selfDb = DatabaseConnection::firstOrCreate(
            ['name' => 'Local SQLite App DB'],
            [
                'driver' => 'sqlite',
                'database_path' => database_path('database.sqlite'),
            ]
        );

        // 4. Example Nightly Backup Schedule
        BackupSchedule::firstOrCreate(
            ['name' => 'Nightly SQLite Self-Backup'],
            [
                'database_connection_id' => $selfDb->id,
                'storage_destination_id' => $localStorage->id,
                'cron_expression' => '0 2 * * *',
                'timezone' => 'UTC',
                'enabled' => true,
                'keep_last_count' => 7,
                'retention_days' => 30,
                'next_run_at' => now()->addDay()->setTime(2, 0),
            ]
        );
    }
}
