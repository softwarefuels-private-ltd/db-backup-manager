<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Services\BackupExecutorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours max for large database dumps

    public function __construct(public BackupRun $backupRun) {}

    public function handle(BackupExecutorService $executor): void
    {
        $executor->execute($this->backupRun);
    }
}
