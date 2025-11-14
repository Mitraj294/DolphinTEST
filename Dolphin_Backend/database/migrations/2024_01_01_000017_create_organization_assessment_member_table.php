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
        Schema::create('organization_assessment_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_assessment_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['Pending', 'Submitted'])->default('Pending');
            $table->timestamps();

            $table->foreign('organization_assessment_id', 'org_assessment_member_org_assessment_fk')
                ->references('id')
                ->on('organization_assessments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_assessment_member');
    }
};
