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
        Schema::create('algorithms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('version')->default(1);
            $table->boolean('is_global')->default(1);
            $table->text('description')->nullable();
            $table->json('self_table')->nullable();
            $table->json('conc_table')->nullable();
            $table->json('adjust_table')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('algorithms');
    }
};
