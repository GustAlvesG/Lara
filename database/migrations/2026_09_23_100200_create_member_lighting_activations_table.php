<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acionamentos de luz feitos pelo próprio sócio, pelo app.
 *
 * O comando em si continua sendo um `home_assistant_overrides` is_quick — é o
 * que o ContactorStateResolver sabe ler. Esta tabela existe por duas coisas que
 * o override não tem:
 *
 * 1. **De quem é a cota.** A regra é um acionamento vigente por sócio; sem
 *    `member_id` não há como saber se ele já tem quadra acesa.
 * 2. **Quem acendeu.** Luz de quadra é custo, e no fim de semana não há reserva
 *    para responder por ela. Aqui fica o histórico.
 *
 * `home_assistant_override_id` é nullable e cai para null quando o override some
 * (o painel pode apagar um comando manual): o histórico do sócio sobrevive ao
 * comando que o originou.
 *
 * Sem FK em `member_id`: `members` vive na conexão mysql e os testes rodam em
 * SQLite; a integridade aqui é de aplicação, como no resto do projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_lighting_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('contactor_id');
            $table->unsignedBigInteger('home_assistant_override_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('released_at')->nullable();
            $table->string('origin', 80)->nullable();
            $table->timestamps();

            // A consulta quente: "este sócio tem quadra acesa agora?"
            $table->index(['member_id', 'ends_at']);
            $table->index(['place_id', 'ends_at']);

            $table->foreign('home_assistant_override_id')
                ->references('id')->on('home_assistant_overrides')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_lighting_activations');
    }
};
