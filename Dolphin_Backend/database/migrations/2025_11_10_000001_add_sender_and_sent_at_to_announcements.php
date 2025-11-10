<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSenderAndSentAtToAnnouncements extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('announcements', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'sender_id')) {
                $table->dropForeign(['sender_id']);
                $table->dropColumn('sender_id');
            }
            if (Schema::hasColumn('announcements', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
        });
    }
}
