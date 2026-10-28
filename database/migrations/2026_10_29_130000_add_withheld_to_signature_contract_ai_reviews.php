<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão automática pelo Claude: os trechos que NÃO foram enviados
 * (valores, CPF, documentos ou endereços ficam de fora, a menos que o gerente
 * marque). Nulo nas revisões antigas = tudo foi enviado. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_contract_ai_reviews') || Schema::hasColumn('signature_contract_ai_reviews', 'withheld')) {
            return;
        }

        Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
            // [{key, label, reasons: [valores|cpf|documentos|endereco]}]
            $table->json('withheld')->nullable()->after('findings');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contract_ai_reviews', 'withheld')) {
            Schema::table('signature_contract_ai_reviews', function (Blueprint $table) {
                $table->dropColumn('withheld');
            });
        }
    }
};
