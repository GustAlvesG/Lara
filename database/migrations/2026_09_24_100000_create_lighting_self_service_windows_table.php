<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horários em que o sócio pode acender a luz sozinho.
 *
 * Saem de `config/home_assistant.php` e viram dado: mudar de 17h para 14h era
 * alteração de código e deploy, e quem decide isso é a diretoria, não a TI.
 *
 * Duas camadas na mesma tabela, distinguidas por `place_id`:
 *
 * - `place_id` nulo: o **padrão do clube**, uma linha por dia da semana.
 * - `place_id` preenchido: a **exceção daquela quadra**. É o caso que motivou
 *   a tabela — quadra coberta escurece antes e precisa de luz mais cedo que a
 *   quadra aberta ao lado, no mesmo dia.
 *
 * `weekday` segue a numeração do Carbon (0 = domingo … 6 = sábado), mais o
 * **7 = feriado**: um dia liberado em `lighting_self_service_dates` que não
 * traga horário próprio cai nesta linha. Assim a quadra coberta também abre
 * cedo no feriado, sem ninguém precisar lembrar de cadastrar a hora.
 *
 * Horários nulos numa linha de quadra significam **fechado naquele dia** — é o
 * que permite calar uma quadra específica sem mexer no padrão. Numa linha de
 * padrão, nulo é o mesmo que não existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lighting_self_service_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id')->nullable();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->timestamps();

            // Uma regra por dia, por quadra. Duas seriam ambiguidade sem
            // resposta boa, e o painel edita a existente.
            $table->unique(['place_id', 'weekday']);

            $table->foreign('place_id')->references('id')->on('places')->cascadeOnDelete();
        });

        // Semente com o que estava no arquivo de configuração, para o painel
        // abrir já mostrando o horário que o clube pratica hoje.
        $rows = [];

        foreach ((array) config('home_assistant.self_service.windows', []) as $weekday => $range) {
            $rows[] = [
                'place_id'   => null,
                'weekday'    => (int) $weekday,
                'starts_at'  => $range[0],
                'ends_at'    => $range[1],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $holiday = (array) config('home_assistant.self_service.holiday_window', []);

        if (count($holiday) === 2) {
            $rows[] = [
                'place_id'   => null,
                'weekday'    => 7,
                'starts_at'  => $holiday[0],
                'ends_at'    => $holiday[1],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('lighting_self_service_windows')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lighting_self_service_windows');
    }
};
