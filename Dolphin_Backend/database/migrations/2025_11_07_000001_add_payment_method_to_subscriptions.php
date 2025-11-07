<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('default_payment_method_id')->nullable()->after('stripe_customer_id');
            $table->string('payment_method_type')->nullable()->after('default_payment_method_id');
            $table->string('payment_method_brand')->nullable()->after('payment_method_type');
            $table->string('payment_method_last4', 8)->nullable()->after('payment_method_brand');
            $table->string('payment_method_label')->nullable()->after('payment_method_last4');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'default_payment_method_id',
                'payment_method_type',
                'payment_method_brand',
                'payment_method_last4',
                'payment_method_label',
            ]);
        });
    }
};
