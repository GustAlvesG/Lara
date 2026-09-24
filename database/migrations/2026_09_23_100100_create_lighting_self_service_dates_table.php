<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exceções de calendário do autoatendimento de iluminação.
 *
 * A regra comum é semanal (sábado e domingo à tarde, em config/home_assistant.php).
 * Esta tabela é para o que o calendário não prevê, nos dois sentidos:
 *
 * - `allow`: feriado ou ponto facultativo em que o clube quer o mesmo
 *   funcionamento de fim de semana — 25/12 numa quinta-feira.
 * - `block`: um sábado de torneio, manutenção ou confraternização, em que a
 *   quadra tem dono e o sócio não deve acender nada.
 *
 * `starts_at`/`ends_at` são só a hora (a data é a da linha). Nulos num `allow`
 * significam "use a janela padrão do fim de semana" — na prática, a de domingo,
 * porque feriado no clube tem o movimento de domingo. Num `block` são ignorados:
 * bloqueio é o dia inteiro, que é como a diretoria pensa o problema.
 *
 * A data é única: duas regras para o mesmo dia seriam uma ambiguidade sem
 * resposta boa ("libera ou bloqueia?"), e o painel edita a linha existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lighting_self_service_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->enum('mode', ['allow', 'block']);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('reason', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['date', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lighting_self_service_dates');
    }
};
