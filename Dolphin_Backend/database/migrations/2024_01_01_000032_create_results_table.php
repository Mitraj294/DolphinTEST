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
        Schema::create('results', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->integer('self_total_words')->nullable();
            $table->integer('conc_total_words')->nullable();
            $table->integer('adj_total_words')->nullable();
            $table->float('self_a')->nullable();
            $table->float('self_b')->nullable();
            $table->float('self_c')->nullable();
            $table->float('self_d')->nullable();
            $table->float('self_avg')->nullable();
            $table->float('dec_approach')->nullable();
            $table->float('conc_a')->nullable();
            $table->float('conc_b')->nullable();
            $table->float('conc_c')->nullable();
            $table->float('conc_d')->nullable();
            $table->float('conc_avg')->nullable();
            $table->timestamp('original_test_timestamp')->nullable()->useCurrent();
            $table->timestamp('latest_test_timestamp')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->integer('tests_taken_count')->nullable()->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
