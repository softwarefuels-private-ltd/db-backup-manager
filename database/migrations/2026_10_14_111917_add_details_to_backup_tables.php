<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->string('database_path')->nullable()->after('password');
            $table->text('extra_options')->nullable()->after('database_path');
        });

        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->unsignedInteger('keep_last_count')->nullable()->default(7)->after('retention_days');
            $table->string('timezone')->default('UTC')->after('cron_expression');
            $table->timestamp('last_run_at')->nullable()->after('retention_days');
            $table->timestamp('next_run_at')->nullable()->after('last_run_at');
        });

        Schema::table('backup_runs', function (Blueprint $table) {
            $table->foreignId('database_connection_id')->nullable()->constrained()->nullOnDelete()->after('backup_schedule_id');
            $table->foreignId('storage_destination_id')->nullable()->constrained()->nullOnDelete()->after('database_connection_id');
            $table->string('trigger_type')->default('scheduled')->after('storage_destination_id');
            $table->unsignedInteger('duration_seconds')->nullable()->after('size');
            $table->longText('output_log')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropForeign(['database_connection_id']);
            $table->dropForeign(['storage_destination_id']);
            $table->dropColumn(['database_connection_id', 'storage_destination_id', 'trigger_type', 'duration_seconds', 'output_log']);
        });

        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropColumn(['keep_last_count', 'timezone', 'last_run_at', 'next_run_at']);
        });

        Schema::table('database_connections', function (Blueprint $table) {
            $table->dropColumn(['database_path', 'extra_options']);
        });
    }
};
