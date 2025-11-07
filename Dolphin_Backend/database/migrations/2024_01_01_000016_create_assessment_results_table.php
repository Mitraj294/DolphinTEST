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
        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_assessment_id')->nullable()->constrained('organization_assessments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('attempt_id')->nullable()->index();
            $table->enum('type', ['original', 'adjust'])->default('original');
            $table->double('self_a')->nullable();
            $table->double('conc_a')->nullable();
            $table->double('task_a')->nullable();
            $table->double('adj_a')->nullable();
            $table->double('self_b')->nullable();
            $table->double('conc_b')->nullable();
            $table->double('task_b')->nullable();
            $table->double('adj_b')->nullable();
            $table->double('self_c')->nullable();
            $table->double('conc_c')->nullable();
            $table->double('task_c')->nullable();
            $table->double('adj_c')->nullable();
            $table->double('self_d')->nullable();
            $table->double('conc_d')->nullable();
            $table->double('task_d')->nullable();
            $table->double('adj_d')->nullable();
            $table->double('self_avg')->nullable();
            $table->double('conc_avg')->nullable();
            $table->double('adj_avg')->nullable();
            $table->double('task_avg')->nullable();
            $table->double('dec_approach')->nullable();
            $table->integer('self_total_count')->nullable();
            $table->integer('conc_total_count')->nullable();
            $table->integer('adj_total_count')->nullable();
            $table->json('self_total_words')->nullable();
            $table->json('conc_total_words')->nullable();
            $table->json('adj_total_words')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
    }
};
