<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria das decisões gravadas no Questor.
 *
 * UMA LINHA POR GRAVAÇÃO EFETIVA — inclusive as que não pegaram linha nenhuma,
 * que são justamente as que alguém vai querer entender depois.
 *
 * Esta tabela existe porque o Questor **não tem onde guardar isso**: lá cabe um
 * único autorizador (`CD_USUARIO_AUTORIZOU`), que é sempre o usuário técnico da
 * Lara. Se três pessoas diferentes aprovarem três ordens, as três aparecem no
 * ERP com o mesmo login. Quem de fato decidiu, e quando, só existe aqui.
 *
 * Os dados do Questor são copiados no momento da decisão (valor, filial, código
 * do usuário técnico) em vez de consultados depois: a ordem no ERP pode mudar,
 * a trilha não.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questor_order_decisions', function (Blueprint $table) {
            $table->id();

            // CD_ORDEM_COMPRA no Questor. Sem foreign key — a ordem vive em
            // outro banco, em outro servidor.
            $table->unsignedInteger('cd_ordem_compra')->index();
            $table->unsignedInteger('cd_filial')->nullable();

            // 'aprovacao' | 'reprovacao'
            $table->string('action', 20);

            // CD_CODUSUARIO usado no carimbo — o valor da configuração NA HORA
            // da decisão. Trocar o usuário técnico depois não reescreve o
            // histórico.
            $table->unsignedInteger('questor_user')->nullable();

            /*
             | Quem decidiu na Lara. Sem foreign key para `users` pelo mesmo
             | motivo de `pix_payments`: o model User fixa a conexão `mysql`,
             | enquanto esta tabela segue a conexão padrão. O nome vai junto
             | como retrato — um usuário renomeado ou removido não apaga a
             | resposta para "quem aprovou isso".
             */
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();

            $table->string('motivo', 255)->nullable();

            // Valor da ordem no momento da decisão, para a trilha responder
            // "quanto foi aprovado" sem depender de reler o ERP.
            $table->decimal('vl_total', 12, 2)->nullable();

            /*
             | Quantas linhas o UPDATE pegou. ZERO é um resultado válido e
             | importante: significa que a ordem saiu da fila entre a abertura
             | da tela e o clique (alguém decidiu pela tela nativa, ou o status
             | mudou). A linha fica registrada mesmo assim.
             */
            $table->unsignedInteger('rows_affected')->default(0);

            // Falso só nas linhas gravadas por engano em modo simulação — que
            // não deveriam existir, e é por isso que o campo existe: para elas
            // serem reconhecíveis se aparecerem.
            $table->boolean('executed')->default(true);

            $table->timestamps();

            // "O que foi decidido nesta ordem, em que ordem?" — a pergunta que
            // a auditoria faz.
            $table->index(['cd_ordem_compra', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questor_order_decisions');
    }
};
