<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os dados que só existem NO ATO da assinatura: as respostas de quem assina,
 * dadas no tablet, e a data da assinatura dos campos automáticos.
 *
 * Ficam separados de `data` (o que o atendente preencheu) de propósito. São
 * duas origens diferentes, e o manifesto precisa poder dizer qual foi qual —
 * "o telefone foi informado pelo signatário" não é a mesma afirmação que "o
 * atendente digitou o telefone".
 *
 * O `body_snapshot` continua sendo gravado no congelamento e não muda mais:
 * ele guarda os marcadores destes campos, e o renderer os resolve a partir
 * daqui. O que muda quando a pessoa responde é o PDF original e o
 * `original_sha256` — que passa a ser o hash do documento JÁ RESPONDIDO, que é
 * o que ela lê antes de assinar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->json('signing_data')->nullable()->after('data');

            // Quando o formulário do tablet foi respondido. Null com modelo
            // que tem pergunta ao signatário = ainda não pode ser assinado.
            $table->timestamp('signing_answered_at')->nullable()->after('signing_data');
        });
    }

    public function down(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn(['signing_data', 'signing_answered_at']);
        });
    }
};
