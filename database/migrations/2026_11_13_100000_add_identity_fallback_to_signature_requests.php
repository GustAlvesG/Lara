<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código por e-mail que não chega (13/11/2026).
     *
     * No tablet, a pessoa toca "Não recebi o código": na primeira vez o código
     * é reenviado; na segunda, ela pode fotografar ou anexar um documento de
     * identidade e seguir com ele no lugar do código. A liberação guarda
     * quantas vezes ela disse que não recebeu e qual anexo fez a conferência —
     * é o que o manifesto imprime.
     *
     * Idempotente: produção e homologação dividem o host, e uma execução
     * interrompida não pode travar a seguinte.
     */
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('signature_requests', 'identity_code_not_received')) {
                $table->unsignedTinyInteger('identity_code_not_received')->default(0)->after('identity_code_sends');
            }

            if (!Schema::hasColumn('signature_requests', 'identity_fallback_attachment_id')) {
                // Sem FK: o anexo é do documento, e a trilha guarda o hash dele.
                $table->unsignedBigInteger('identity_fallback_attachment_id')->nullable()->after('identity_code_not_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            foreach (['identity_fallback_attachment_id', 'identity_code_not_received'] as $coluna) {
                if (Schema::hasColumn('signature_requests', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
