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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index()->after('subscription_status');
            $table->timestamp('last_active_at')->nullable()->after('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('transaction_id')->constrained('plans')->nullOnDelete();
            $table->string('billing_type', 20)->default('monthly')->after('status');
            $table->boolean('auto_renewal')->default(true)->after('billing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'billing_type', 'auto_renewal']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_active_at']);
        });
    }
};
