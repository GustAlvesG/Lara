<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão automática pelo Claude (04/11/2026): os trechos que quem pediu
 * anexou ao pedido — proposta do fornecedor, contrato anterior, orientação
 * do jurídico — e que foram junto com a minuta. Nulo nas revisões antigas =
 * nenhum trecho anexado. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_contract_ai_reviews') || Schema::hasColumn('signature_contract_ai_reviews', 'extras')) {
            return;
        }

        Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
            // [{title, text}]
            $table->json('extras')->nullable()->after('withheld');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contract_ai_reviews', 'extras')) {
            Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
                $table->dropColumn('extras');
            });
        }
    }
};
