<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lote sem limite de ingressos (28/10/2026): `ticket_lots.quantity` nulo = o
 * lote não esgota (fecha pela data ou pelo "Virar lote agora"). A lotação do
 * evento, se houver, continua valendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_lots', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volta a obrigatória só se nenhum lote estiver sem limite.
        if (\Illuminate\Support\Facades\DB::table('ticket_lots')->whereNull('quantity')->exists()) {
            throw new RuntimeException('Há lote sem limite de ingressos: defina a quantidade antes de desfazer.');
        }

        Schema::table('ticket_lots', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
        });
    }
};
