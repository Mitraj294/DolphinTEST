<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organization_assessments', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_assessments', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('time');
            }
            if (!Schema::hasColumn('organization_assessments', 'send_at')) {
                $table->timestamp('send_at')->nullable()->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('organization_assessments', 'send_at')) {
                $table->dropColumn('send_at');
            }
            if (Schema::hasColumn('organization_assessments', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
