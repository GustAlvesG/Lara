<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O período trabalhado passa a ser informado por horário de início e fim.
     * `total_hours` deixa de ser digitado e passa a ser derivado desses
     * horários, assim como `end_date` (que é start_date, ou start_date + 1 dia
     * quando o turno vira a meia-noite).
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->time('start_time')->after('start_date');
            $table->time('end_time')->after('end_date');
        });

        // end_date agora é sempre derivado, então nunca fica nulo.
        DB::statement('ALTER TABLE freelancer_services MODIFY end_date DATE NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE freelancer_services MODIFY end_date DATE NULL');

        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
