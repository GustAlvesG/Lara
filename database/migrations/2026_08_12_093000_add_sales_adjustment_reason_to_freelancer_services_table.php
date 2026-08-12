<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Justificativa da alteração do valor apurado na comissão de venda.
     *
     * O valor de venda vem do MultiVendas e pré-preenche a tela, mas continua
     * editável — e tem de continuar: o caixa pode ter fechado fora do horário do
     * contrato, e uma venda pode ter sido cancelada depois. O que faltava era a
     * contrapartida: quem muda o número apurado diz **por quê**.
     *
     * Isso não é log de auditoria — é cláusula. O texto vai para o corpo do termo
     * que as partes assinam, ao lado do Anexo I com o relatório original, para que
     * o freelancer leia a diferença e o motivo dela antes de assinar. Por isso a
     * coluna fica no documento, e não numa tabela de trilha.
     *
     * Só é gravada quando há relatório E o valor considerado difere dele — o
     * mesmo critério que faz `sales_source` voltar a `manual`. Sem relatório não
     * há valor de origem a alterar: o documento já diz que o número foi informado
     * pelo CONTRATANTE.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('sales_adjustment_reason', 500)->nullable()->after('sales_report');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('sales_adjustment_reason');
        });
    }
};
