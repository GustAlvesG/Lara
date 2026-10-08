<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O relatório de validação do documento assinado pelo gov.br.
     *
     * No tablet, o manifesto é a última página do PDF final. No gov.br não
     * pode ser: acrescentar uma página ao arquivo assinado desfaria as
     * assinaturas. Então o relatório é um PDF à parte, com o hash próprio.
     */
    public function up(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->string('report_path')->nullable()->after('final_sha256');
            $table->char('report_sha256', 64)->nullable()->after('report_path');
        });
    }

    public function down(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn(['report_path', 'report_sha256']);
        });
    }
};
