<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Transforma os overrides de "1 contator / 1 janela" para agendamentos ricos:
     * múltiplos locais (contactors), múltiplos dias (weekdays) e múltiplas janelas de horário.
     */
    public function up(): void
    {
        // 1. Novas colunas no agendamento
        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->unsignedInteger('priority')->default(0)->after('mode');
            $table->date('start_date')->nullable()->after('priority');
            $table->date('end_date')->nullable()->after('start_date');
            // Distingue ações rápidas (Ligar/Desligar agora) de agendamentos nomeados
            $table->boolean('is_quick')->default(false)->after('end_date');
        });

        // 2. Pivot: agendamento <-> contactors (múltiplos locais)
        Schema::create('home_assistant_override_contactor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->unsignedBigInteger('contactor_id');
            $table->foreign('home_assistant_override_id', 'hao_contactor_override_fk')
                ->references('id')->on('home_assistant_overrides')->cascadeOnDelete();
            $table->foreign('contactor_id', 'hao_contactor_contactor_fk')
                ->references('id')->on('contactors')->cascadeOnDelete();
            $table->unique(['home_assistant_override_id', 'contactor_id'], 'hao_contactor_unique');
        });

        // 3. Pivot: agendamento <-> weekdays (múltiplos dias; vazio = todos os dias)
        Schema::create('home_assistant_override_weekday', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->unsignedBigInteger('weekday_id');
            $table->foreign('home_assistant_override_id', 'hao_weekday_override_fk')
                ->references('id')->on('home_assistant_overrides')->cascadeOnDelete();
            $table->foreign('weekday_id', 'hao_weekday_weekday_fk')
                ->references('id')->on('weekdays')->cascadeOnDelete();
            $table->unique(['home_assistant_override_id', 'weekday_id'], 'hao_weekday_unique');
        });

        // 4. Janelas de horário (múltiplos horários por agendamento)
        Schema::create('home_assistant_override_windows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_assistant_override_id');
            $table->foreign('home_assistant_override_id', 'hao_window_override_fk')
                ->references('id')->on('home_assistant_overrides')->cascadeOnDelete();
            $table->time('turn_on_at');
            $table->time('turn_off_at');
            $table->timestamps();
        });

        // 5. Migra dados existentes para a nova estrutura
        $existing = DB::table('home_assistant_overrides')->get();
        foreach ($existing as $row) {
            // Vincula o contator único ao pivot
            if (!empty($row->contactor_id)) {
                DB::table('home_assistant_override_contactor')->insert([
                    'home_assistant_override_id' => $row->id,
                    'contactor_id'               => $row->contactor_id,
                ]);
            }

            // Migra a janela única, se houver
            if (!empty($row->turn_on_at) && !empty($row->turn_off_at)) {
                DB::table('home_assistant_override_windows')->insert([
                    'home_assistant_override_id' => $row->id,
                    'turn_on_at'                 => $row->turn_on_at,
                    'turn_off_at'                => $row->turn_off_at,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);
            }

            // expires_at -> end_date; start_date = data de criação
            DB::table('home_assistant_overrides')->where('id', $row->id)->update([
                'start_date' => $row->created_at ? date('Y-m-d', strtotime($row->created_at)) : date('Y-m-d'),
                'end_date'   => $row->expires_at ? date('Y-m-d', strtotime($row->expires_at)) : null,
                'is_quick'   => in_array($row->mode, ['manual_on', 'manual_off']) ? 1 : 0,
            ]);
        }

        // 6. Remove colunas antigas
        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->dropForeign(['contactor_id']);
            $table->dropColumn(['contactor_id', 'turn_on_at', 'turn_off_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        // Restaura colunas antigas
        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->unsignedBigInteger('contactor_id')->nullable()->after('mode');
            $table->time('turn_on_at')->nullable();
            $table->time('turn_off_at')->nullable();
            $table->dateTime('expires_at')->nullable();
        });

        // Restaura o primeiro contator/janela de volta para as colunas
        $overrides = DB::table('home_assistant_overrides')->get();
        foreach ($overrides as $ov) {
            $contactorId = DB::table('home_assistant_override_contactor')
                ->where('home_assistant_override_id', $ov->id)->value('contactor_id');
            $window = DB::table('home_assistant_override_windows')
                ->where('home_assistant_override_id', $ov->id)->first();

            DB::table('home_assistant_overrides')->where('id', $ov->id)->update([
                'contactor_id' => $contactorId,
                'turn_on_at'   => $window->turn_on_at ?? null,
                'turn_off_at'  => $window->turn_off_at ?? null,
                'expires_at'   => $ov->end_date ? $ov->end_date . ' 23:59:59' : now()->endOfDay(),
            ]);
        }

        Schema::dropIfExists('home_assistant_override_windows');
        Schema::dropIfExists('home_assistant_override_weekday');
        Schema::dropIfExists('home_assistant_override_contactor');

        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->foreign('contactor_id')->references('id')->on('contactors')->cascadeOnDelete();
            $table->dropColumn(['name', 'priority', 'start_date', 'end_date', 'is_quick']);
        });
    }
};
