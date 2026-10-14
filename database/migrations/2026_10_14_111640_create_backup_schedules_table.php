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
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storage_destination_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('cron_expression');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
