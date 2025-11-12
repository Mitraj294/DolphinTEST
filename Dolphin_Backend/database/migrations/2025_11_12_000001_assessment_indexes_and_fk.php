<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add composite index to assessment_responses for faster lookups
        try {
            Schema::table('assessment_responses', function (Blueprint $table) {
                $table->index(['user_id', 'attempt_id', 'assessment_id'], 'idx_assessment_responses_user_attempt');
            });
        } catch (\Throwable $e) {
            // Index may already exist; safe to ignore.
        }

        // Add composite index to assessment_results
        try {
            Schema::table('assessment_results', function (Blueprint $table) {
                $table->index(['user_id', 'attempt_id'], 'idx_assessment_results_user_attempt');
            });
        } catch (\Throwable $e) {
            // Index may already exist; safe to ignore.
        }

        // Add FK for assessment_times.assessment_response_id -> assessment_responses.id
        try {
            Schema::table('assessment_times', function (Blueprint $table) {
                $table->foreign('assessment_response_id', 'fk_assessment_times_response')
                      ->references('id')->on('assessment_responses')
                      ->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            // Foreign key may already exist; safe to ignore.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('assessment_responses', function (Blueprint $table) {
                $table->dropIndex('idx_assessment_responses_user_attempt');
            });
        } catch (\Throwable $e) {
            // Ignore: index might not exist.
        }
        try {
            Schema::table('assessment_results', function (Blueprint $table) {
                $table->dropIndex('idx_assessment_results_user_attempt');
            });
        } catch (\Throwable $e) {
            // Ignore: index might not exist.
        }
        try {
            Schema::table('assessment_times', function (Blueprint $table) {
                $table->dropForeign('fk_assessment_times_response');
            });
        } catch (\Throwable $e) {
            // Ignore: foreign key might not exist.
        }
    }
};
