<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visto dispensado num documento — o caminho do gov.br para modelo com visto.
 *
 * O visto é regra do MODELO (`requires_initials`), e o modelo não muda por
 * causa de um documento. Quando um documento de modelo com visto é preparado
 * para o gov.br, a dispensa fica registrada nele: a assinatura do gov.br
 * cobre o arquivo inteiro, e o visto por página só existe no tablet.
 *
 * Quem lê o visto pergunta ao documento (`SignatureDocument::requiresInitials`),
 * nunca direto ao modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_documents', 'initials_waived_at')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->timestamp('initials_waived_at')->nullable()->after('govbr_sent_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_documents', 'initials_waived_at')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropColumn('initials_waived_at');
            });
        }
    }
};
