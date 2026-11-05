<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos (05/11/2026): vigência e aviso de término.
 *
 *  - O prazo fica no conteúdo (`content.validity`: meses a partir da
 *    assinatura ou data de término fixa). Quando o documento termina de ser
 *    assinado, o Lara grava `signed_at` e calcula `expires_on`.
 *  - `expiry_alerts`: os avisos de término já mandados (60, 30 e 15 dias),
 *    para não repetir.
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contracts') && !Schema::hasColumn('signature_contracts', 'expires_on')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->timestamp('signed_at')->nullable()->after('approved_by_name');
                $table->date('expires_on')->nullable()->after('signed_at');
                $table->json('expiry_alerts')->nullable()->after('expires_on');
                $table->index('expires_on', 'sig_contracts_expires_on_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contracts', 'expires_on')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->dropIndex('sig_contracts_expires_on_idx');
                $table->dropColumn(['signed_at', 'expires_on', 'expiry_alerts']);
            });
        }
    }
};
