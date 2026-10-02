<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O nome do atendente, gravado como TEXTO no documento.
 *
 * A página de manifesto precisa dizer quem conduziu o atendimento, e
 * `created_by` sozinho não serve para isso: o model User fixa a conexão
 * `mysql`, enquanto este módulo segue a conexão padrão — o job de finalização
 * não pode depender de um join entre bancos diferentes para montar um PDF.
 *
 * É também um RETRATO, e não só uma conveniência: o funcionário sai do clube,
 * troca de nome, tem o cadastro corrigido — e o documento assinado continua
 * tendo de dizer quem estava no balcão naquele dia. Mesma decisão de
 * `cotacao_mapas.solicitante` e do `signed_snapshot` dos contratos de
 * freelancer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->string('created_by_name', 150)->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn('created_by_name');
        });
    }
};
