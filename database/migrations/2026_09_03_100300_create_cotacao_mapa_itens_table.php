<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As LINHAS do mapa: um item da solicitação (ou um item avulso que o comprador
 * acrescentou), com o retrato da última compra ao lado.
 *
 * DUAS COISAS EXPLICAM O DESENHO DESTA TABELA:
 *
 * 1. `questor_cd_material` é NULO-ável porque ele é nulo no próprio Questor:
 *    TBL_COMPRAS_SOLICITACAO_ITENS.CD_MATERIAL aceita NULL, e o solicitante
 *    pode ter digitado o item como texto livre em DS_MATERIAL. Esse item não
 *    tem histórico de compra por código — e mesmo assim precisa aparecer no
 *    mapa, com a área de histórico dizendo "sem cadastro".
 *
 * 2. Os campos `ult_compra_*` são um RETRATO gravado na importação, não uma
 *    consulta viva. O mapa tem de ser reproduzível meses depois, mesmo que
 *    notas novas entrem no Questor no meio da cotação. Quem quiser o dado
 *    atual usa o botão "atualizar histórico", que regrava o retrato de
 *    propósito e deixa registro no log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacao_mapa_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotacao_mapa_id')
                ->constrained('cotacao_mapas')
                ->cascadeOnDelete();

            // CD_ITEM e CD_MATERIAL na solicitação do Questor. Ambos nulos no
            // item avulso; o segundo também no item de texto livre.
            $table->unsignedInteger('questor_cd_item')->nullable();
            $table->unsignedInteger('questor_cd_material')->nullable()->index();

            $table->string('descricao', 255);
            $table->string('unidade', 10)->nullable();
            $table->decimal('quantidade', 18, 4)->default(0);

            $table->unsignedSmallInteger('ordem')->default(0);

            $table->enum('origem', ['solicitacao', 'avulso'])->default('solicitacao');

            // Retrato da última compra (query 3). Ver o comentário do topo.
            $table->date('ult_compra_data')->nullable();
            $table->unsignedInteger('ult_compra_fornecedor_id')->nullable();
            $table->string('ult_compra_fornecedor_nome', 150)->nullable();
            $table->decimal('ult_compra_valor', 18, 6)->nullable();
            $table->string('ult_compra_nf', 20)->nullable();
            $table->string('ult_compra_unidade', 10)->nullable();

            /*
             | A decisão do comprador: de quem comprar ESTE item. Fica no item e
             | não no mapa porque a compra pode ser dividida — é justamente a
             | comparação que o rodapé "TOTAL GERAL DO PEDIDO" existe para
             | permitir.
             |
             | nullOnDelete: apagar uma coluna de fornecedor não pode apagar a
             | linha do item; ela só perde o vencedor.
             */
            $table->foreignId('vencedor_id')->nullable()
                ->constrained('cotacao_mapa_fornecedores')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['cotacao_mapa_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_mapa_itens');
    }
};
