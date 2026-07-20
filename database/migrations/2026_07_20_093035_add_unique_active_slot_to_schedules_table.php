<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Coluna gerada: só carrega um valor quando o agendamento está "ativo"
        // (mesmo critério usado no código: status_id fora de 0/4). Fora disso é NULL,
        // e um índice único permite múltiplos NULLs — então cancelar/expirar libera
        // o horário para reuso, mas dois agendamentos ativos para o mesmo
        // place_id+start+end nunca coexistem, mesmo sob concorrência.
        DB::statement("ALTER TABLE schedules ADD COLUMN active_slot_key VARCHAR(191)
            GENERATED ALWAYS AS (
                CASE WHEN status_id IS NULL OR status_id NOT IN (0,4)
                     THEN CONCAT(place_id,'|',start_schedule,'|',end_schedule)
                     ELSE NULL END
            ) VIRTUAL");

        Schema::table('schedules', function (Blueprint $table) {
            $table->unique('active_slot_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique(['active_slot_key']);
            $table->dropColumn('active_slot_key');
        });
    }
};
