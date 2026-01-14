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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('cpf');
            $table->date('admission_date');
            $table->string('position');
            $table->string('department');
            //Soft delete
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->date('entry_date');
            $table->string('reference_time');
            //Entry times as string
            $table->string('entry_times');
            $table->string('type');
            $table->integer('amount_minutes');
            $table->integer('balance_minutes');
            $table->date('due_date')->nullable();
            $table->timestamps();

        });

        Schema::create('time_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_time_to_adjust_id');
            $table->foreign('entry_time_to_adjust_id')->references('id')->on('time_entries');
            $table->unsignedBigInteger('entry_time_adjusted_id');
            $table->foreign('entry_time_adjusted_id')->references('id')->on('time_entries');
            $table->integer('amount_minutes');
            $table->integer('before_adjustment_minutes');
            $table->integer('after_adjustment_minutes');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_adjustments', function (Blueprint $table) {
            $table->dropForeign(['entry_time_to_adjust_id']);
            $table->dropColumn('entry_time_to_adjust_id');
            $table->dropForeign(['entry_time_adjusted_id']);
            $table->dropColumn('entry_time_adjusted_id');
            $table->dropColumn('amount_minutes');
            $table->dropColumn('before_adjustment_minutes');
            $table->dropColumn('after_adjustment_minutes');
            $table->dropColumn('reason');
        });
        Schema::dropIfExists('time_adjustments');

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->dropColumn('entry_date');
            $table->dropColumn('reference_time');
            $table->dropColumn('entry_times');
            $table->dropColumn('type');
            $table->dropColumn('amount_minutes');
            $table->dropColumn('balance_minutes');
            $table->dropColumn('due_date');
        });
        Schema::dropIfExists('time_entries');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('cpf');
            $table->dropColumn('admission_date');
            $table->dropColumn('position');
            $table->dropColumn('department');
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('employees');
    }
};
