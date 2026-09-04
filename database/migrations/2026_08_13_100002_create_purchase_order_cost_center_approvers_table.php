<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relação centro de custo → diretores.
 *
 * **É um padrão sugerido, não uma regra fechada.** Quem tem a palavra final
 * sobre quem vai decidir uma ordem é a Gerência, no momento em que aprova o
 * nível 2: a tela chega preenchida com os diretores ligados aos centros de
 * custo dos itens, e o gerente confirma ou troca. Para as ordens sem centro de
 * custo — quase um terço da fila hoje — não há sugestão nenhuma e a escolha é
 * inteiramente dele.
 *
 * Por isso esta tabela não guarda quem decidiu nada: ela alimenta o formulário.
 * Quem de fato foi escolhido, e se veio da sugestão ou da mão do gerente, é
 * registrado no processo de aprovação.
 *
 * `cd_centro_custo` é o código no Questor (`SEL_CUSTOS_CENTRO_CUSTOS`), sem
 * foreign key: o cadastro vive em outro banco, em outro servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_cost_center_approvers', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('cd_centro_custo')->index();

            // Sem foreign key para `users` pelo mesmo motivo de `pix_payments`:
            // o model User fixa a conexão `mysql`, e esta tabela segue a padrão.
            $table->unsignedBigInteger('user_id')->index();

            /*
             | Desvincular sem apagar. Um diretor que sai da alçada de um centro
             | de custo vira `active = false` em vez de sumir: as ordens que ele
             | já decidiu continuam fazendo sentido quando alguém for ler.
             */
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Um diretor aparece uma vez por centro de custo — a tela é de
            // marcar e desmarcar, e clicar duas vezes não pode criar duas
            // linhas que depois divergem no `active`.
            //
            // Nome curto na mão: o gerado por convenção passaria dos 64
            // caracteres que o MySQL aceita como identificador.
            $table->unique(['cd_centro_custo', 'user_id'], 'poca_centro_usuario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_cost_center_approvers');
    }
};
