<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_responses', 'organization_assessment_id')) {
                $table->foreignId('organization_assessment_id')->nullable()->constrained('organization_assessments')->nullOnDelete()->after('assessment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_responses', 'organization_assessment_id')) {
                $table->dropForeign(['organization_assessment_id']);
                $table->dropColumn('organization_assessment_id');
            }
        });
    }
};
