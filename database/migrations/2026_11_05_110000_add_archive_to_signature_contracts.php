<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos (05/11/2026): arquivar. Decisão do usuário: arquivar só TIRA DA
 * LISTA — o contrato sai das abas do dia a dia e vai para "Arquivados", sem
 * mudar nada nele, e pode ser desarquivado. Qualquer contrato, em qualquer
 * situação; o motivo fica no histórico (evento `archived`).
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contracts') && !Schema::hasColumn('signature_contracts', 'archived_at')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('canceled_at');
                $table->string('archived_by_name', 120)->nullable()->after('archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contracts', 'archived_at')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->dropColumn(['archived_at', 'archived_by_name']);
            });
        }
    }
};
