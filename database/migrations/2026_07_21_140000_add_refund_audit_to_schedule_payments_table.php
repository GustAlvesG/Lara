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
        Schema::table('schedule_payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('paid_amount');
            $table->unsignedBigInteger('refunded_by')->nullable()->after('status_id');
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');

            $table->foreign('refunded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['refunded_amount', 'refunded_by', 'refunded_at']);
        });
    }
};
