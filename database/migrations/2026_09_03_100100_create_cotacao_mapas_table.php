<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cabeçalho do mapa de cotação — o equivalente às linhas 1 a 4 da planilha
 * que a compra usa hoje.
 *
 * O mapa nasce de uma Solicitação de Compra do Questor, mas NÃO é a
 * solicitação: o Questor é somente leitura para este módulo. O que se guarda
 * aqui é o vínculo (`questor_solicitacao`) e um retrato dos dados que vão para
 * o papel — solicitante e departamento vêm como texto, não como código.
 *
 * O retrato é deliberado: a SC pode ser editada, cancelada ou ter o solicitante
 * corrigido no ERP depois, e um mapa impresso e assinado precisa continuar
 * dizendo o que dizia no dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacao_mapas', function (Blueprint $table) {
            $table->id();

            /*
             | CD_SOLICITACAO / CD_EMPRESA / CD_FILIAL no Questor. Sem foreign
             | key: a solicitação vive noutro banco, noutro servidor, e nunca é
             | escrita por aqui.
             */
            $table->unsignedInteger('questor_solicitacao')->index();
            $table->unsignedInteger('questor_empresa')->nullable();
            $table->unsignedInteger('questor_filial')->nullable();

            $table->string('titulo', 255);

            // Retratos de DS_SOLICITANTE e do departamento, no momento da
            // importação. Ver o comentário do topo.
            $table->string('solicitante', 100)->nullable();
            $table->string('departamento', 100)->nullable();

            // Quem está cotando. Texto e não FK: o comprador que assina o mapa
            // nem sempre é o usuário logado que o gerou.
            $table->string('comprador', 100)->nullable();

            $table->date('data_mapa');

            $table->enum('status', ['rascunho', 'em_cotacao', 'fechado', 'cancelado'])
                ->default('rascunho');

            $table->text('observacoes')->nullable();

            /*
             | Autor na Lara. Sem foreign key para `users` pelo mesmo motivo de
             | `questor_order_decisions`: o model User fixa a conexão `mysql`,
             | enquanto esta tabela segue a conexão padrão.
             */
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            /*
             | "Já existe mapa aberto para esta SC?" — a pergunta que a
             | importação faz antes de duplicar o trabalho do comprador.
             |
             | Índice comum, não único: a regra real é "um mapa NÃO CANCELADO
             | por solicitação", que é um índice parcial. MySQL não tem índice
             | parcial, então a regra é validada na aplicação
             | (MapaImportService::mapaAbertoDe) e o índice aqui só serve à
             | consulta.
             */
            $table->index(['questor_solicitacao', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_mapas');
    }
};
