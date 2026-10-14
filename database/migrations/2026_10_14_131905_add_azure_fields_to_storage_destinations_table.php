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
        Schema::table('storage_destinations', function (Blueprint $table) {
            $table->text('connection_string')->nullable()->after('root');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_destinations', function (Blueprint $table) {
            $table->dropColumn(['connection_string']);
        });
    }
};
