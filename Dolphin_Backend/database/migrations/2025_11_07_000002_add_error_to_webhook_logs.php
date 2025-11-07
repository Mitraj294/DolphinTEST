<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('webhook_logs')) {
            return; // safety
        }
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->text('error')->nullable()->after('processed');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('webhook_logs')) {
            return; // safety
        }
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
