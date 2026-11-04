<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão jurídica pelo Claude, da Gerência (04/11/2026): o nível de risco,
 * um resumo e as possíveis fraquezas do contrato para o clube; em risco alto,
 * a orientação de procurar um jurídico especializado. Nulo = a revisão não
 * teve a parte jurídica (pedida por quem redige, ou antes de existir).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_contract_ai_reviews') || Schema::hasColumn('signature_contract_ai_reviews', 'legal')) {
            return;
        }

        Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
            // {risk: baixo|medio|alto, summary, weaknesses: [{clausula, point, suggestion}], seek_lawyer: bool}
            $table->json('legal')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contract_ai_reviews', 'legal')) {
            Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
                $table->dropColumn('legal');
            });
        }
    }
};
