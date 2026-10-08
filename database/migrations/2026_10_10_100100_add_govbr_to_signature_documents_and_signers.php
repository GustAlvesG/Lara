<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinatura pelo gov.br concluindo a assinatura no Lara.
     *
     * - `signature_documents.govbr_sent_at`: quando o atendente preparou o
     *   documento para o gov.br. A partir daí o documento é assinado só por lá
     *   (o tablet não libera mais) e o prazo passa a ser o do gov.br.
     * - `signature_documents.govbr_check_id`: a conferência cujo arquivo é o
     *   PDF FINAL — o que voltou do gov.br com todas as assinaturas.
     * - `signature_signers.govbr_check_id`: a conferência pela qual a pessoa
     *   assinou. Nulo = assinou no tablet (ou ainda não assinou).
     *
     * Sem foreign key: documento e conferência apontam um para o outro, e a
     * conferência já tem FK para o documento.
     */
    public function up(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->timestamp('govbr_sent_at')->nullable();
            $table->unsignedBigInteger('govbr_check_id')->nullable();
        });

        Schema::table('signature_signers', function (Blueprint $table) {
            $table->unsignedBigInteger('govbr_check_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('signature_signers', function (Blueprint $table) {
            $table->dropIndex(['govbr_check_id']);
            $table->dropColumn('govbr_check_id');
        });

        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn(['govbr_sent_at', 'govbr_check_id']);
        });
    }
};
