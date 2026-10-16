<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diário dos atendimentos do O Lara: cada conversa que chega a ele, sai dele
 * ou é encerrada, com o motivo (App\Services\PoliBot\AttendanceJournal).
 *
 * Nasceu de 16/10/2026: o distribute para a Secretaria voltava para O Lara
 * "pelo sistema" e a Lara reabria o menu, em ciclo — e nada no banco dizia
 * por que uma conversa tinha ido ou voltado.
 *
 * `ending_reason` em bot_sessions guarda por que a conversa está esperando o
 * encerramento diferido (fim do fluxo, transbordo devolvido, aviso do carro):
 * é o motivo do close que o agendamento faz minutos depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('contact_uuid')->index();
            $table->string('contact_phone')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('attendance_uuid')->nullable()->index();

            // recebida (chegou ao O Lara) | enviada (saiu dele) | encerramento
            $table->string('kind', 20);
            $table->string('reason', 40);
            $table->string('detail', 500)->nullable();
            $table->string('actor')->nullable();
            $table->string('target')->nullable();

            // Só das enviadas: aguardando | atendente | fila | devolvido | falhou
            $table->string('outcome', 20)->nullable();
            $table->string('outcome_detail')->nullable();

            // A mensagem de sistema da Poli que confirmou o evento.
            $table->string('poli_message_id')->nullable()->index();
            $table->string('poli_text', 500)->nullable();

            $table->string('flow_slug')->nullable();
            $table->string('step_key')->nullable();
            $table->timestamps();

            $table->index(['kind', 'reason'], 'bot_attendance_events_tipo_motivo_idx');
            $table->index(['contact_uuid', 'kind', 'occurred_at'], 'bot_attendance_events_contato_idx');
        });

        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->string('ending_reason', 40)->nullable()->after('ending_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropColumn('ending_reason');
        });

        Schema::dropIfExists('bot_attendance_events');
    }
};
