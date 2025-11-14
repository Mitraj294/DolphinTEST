<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organization_assessment_member', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_assessment_member', 'notified_at')) {
                $table->timestamp('notified_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_assessment_member', function (Blueprint $table) {
            if (Schema::hasColumn('organization_assessment_member', 'notified_at')) {
                $table->dropColumn('notified_at');
            }
        });
    }
};
