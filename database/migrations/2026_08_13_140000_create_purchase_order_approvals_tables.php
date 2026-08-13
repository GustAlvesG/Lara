<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O processo de aprovação de uma ordem de compra e as decisões dentro dele.
 *
 * O Questor guarda um autorizador só. Todo o resto — quantos níveis, quem
 * decidiu cada um, quando, e o que foi escolhido pelo caminho — mora aqui.
 *
 * Duas tabelas porque são duas coisas com tempos diferentes: o processo é um
 * por ordem, e as decisões são muitas, inclusive em paralelo no nível 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();

            // CD_ORDEM_COMPRA no Questor. Sem foreign key: outro banco, outro
            // servidor. Uma ordem pode ter mais de um processo ao longo do
            // tempo (reprovada e reaberta), então não é único.
            $table->unsignedInteger('cd_ordem_compra')->index();
            $table->unsignedInteger('cd_filial')->nullable();

            /*
             | Retrato da ordem no início do processo. É o que responde, na hora
             | de fechar, se o que foi aprovado ainda é o que vai ser gravado —
             | alguém pode ter editado itens ou valor no Questor no meio do
             | caminho, e aprovar R$ 3 mil não autoriza R$ 30 mil.
             */
            $table->decimal('vl_total', 12, 2)->nullable();
            $table->unsignedInteger('nr_itens')->nullable();

            /*
             | Centros de custo dos itens, congelados no início. A relação
             | CC -> diretor pode mudar depois; o que valeu para este processo
             | foi o que estava valendo quando ele começou.
             */
            $table->json('cost_centers')->nullable();

            // A ordem tem algum item sem centro de custo? Quase um terço da
            // fila tem. Para essas não há sugestão, e a escolha do nível 3 é
            // inteiramente do gerente.
            $table->boolean('sem_centro_custo')->default(false);

            // open | approved | rejected
            $table->string('status', 12)->default('open');

            // 1 = Contabilidade, 2 = Gerência, 3 = Diretoria.
            $table->unsignedTinyInteger('current_level')->default(1);

            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            // A gravação no Questor que este processo produziu. Nulo enquanto
            // ele não fecha, e também quando fecha reprovado.
            $table->unsignedBigInteger('questor_decision_id')->nullable();

            $table->timestamps();

            // "Existe processo aberto para esta ordem?" — a pergunta feita
            // antes de cada decisão.
            $table->index(['cd_ordem_compra', 'status']);
        });

        Schema::create('purchase_order_approval_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_id')
                ->constrained('purchase_order_approvals')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('level');

            /*
             | Quem responde pelo passo.
             |
             | Níveis 1 e 2 são CARGOS, não pessoas: `role` guarda qual, e
             | `user_id` fica nulo até alguém decidir. Assim, trocar o
             | coordenador da Contabilidade não deixa processos apontando para
             | quem saiu.
             |
             | Nível 3 é pessoa: cada diretor escolhido tem seu próprio passo,
             | com `user_id` preenchido desde o começo.
             */
            $table->string('role', 40)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            /*
             | O diretor veio da relação centro de custo -> diretor
             | (`suggested`) ou foi escolhido na mão pelo gerente (`manual`)?
             |
             | Não é curiosidade: o gerente pode trocar o aprovador da ordem que
             | ele mesmo aprovou no nível 2. Sem registrar a origem, ninguém
             | consegue distinguir depois "seguiu o cadastro" de "escolheu quem
             | quis".
             */
            $table->string('source', 12)->nullable();

            // pending | approved | rejected | skipped
            $table->string('decision', 12)->default('pending');

            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['approval_id', 'level']);
            // "O que está esperando esta pessoa?" — a fila do diretor.
            $table->index(['user_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approval_steps');
        Schema::dropIfExists('purchase_order_approvals');
    }
};
