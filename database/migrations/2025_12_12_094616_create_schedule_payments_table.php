<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Define se a migration deve ser executada dentro de uma transação.
     * Se qualquer passo falhar, tudo é revertido (rollback).
     */
    public $withinTransaction = true;
    
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->decimal('paid_amount', 10, 2);
            $table->string('payment_integration_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            //Foreign key to status table
            $table->integer('status_id')->nullable();
            $table->foreign('status_id')->references('id')->on('status')->onDelete('set null');
            $table->timestamps();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_payment_id')->nullable()->after('status_id');
            $table->foreign('schedule_payment_id')->references('id')->on('schedule_payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['schedule_payment_id']);
            $table->dropColumn('schedule_payment_id');
        });

        Schema::dropIfExists('schedule_payments');

    }
};