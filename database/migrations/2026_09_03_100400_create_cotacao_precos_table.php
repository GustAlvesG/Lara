<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As CÉLULAS do mapa: o cruzamento de um item com um fornecedor.
 *
 * `situacao` é o campo que a planilha atual não tem, e é o que impede o erro
 * mais caro do mapa em papel:
 *
 *   cotado       — há preço. `valor_unitario` preenchido.
 *   nao_trabalha — o "NT" da planilha. O fornecedor NÃO vende este item.
 *   sem_resposta — célula vazia. Ele foi consultado e não respondeu (ainda).
 *
 * Distinguir os dois últimos importa na hora de comparar totais: quem não
 * trabalha o item não deveria ser penalizado como se tivesse ignorado a
 * cotação, e nenhum dos dois pode virar zero em conta nenhuma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacao_precos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotacao_mapa_item_id')
                ->constrained('cotacao_mapa_itens')
                ->cascadeOnDelete();

            $table->foreignId('cotacao_mapa_fornecedor_id')
                ->constrained('cotacao_mapa_fornecedores')
                ->cascadeOnDelete();

            // Nulo em 'nao_trabalha' e 'sem_resposta'. Nunca zero para dizer
            // "não tem" — zero é um preço.
            $table->decimal('valor_unitario', 18, 6)->nullable();

            $table->enum('situacao', ['cotado', 'nao_trabalha', 'sem_resposta'])
                ->default('sem_resposta');

            // O fornecedor pode ofertar marca diferente da pedida; sem isto o
            // comprador compara preço de coisas que não são a mesma coisa.
            $table->string('marca', 80)->nullable();
            $table->string('observacao', 255)->nullable();

            $table->timestamps();

            // Uma célula por cruzamento. A grade salva por célula (upsert), e
            // este índice é o que garante que dois cliques rápidos não criem
            // duas linhas para o mesmo cruzamento.
            $table->unique(
                ['cotacao_mapa_item_id', 'cotacao_mapa_fornecedor_id'],
                'cotacao_precos_celula_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_precos');
    }
};
