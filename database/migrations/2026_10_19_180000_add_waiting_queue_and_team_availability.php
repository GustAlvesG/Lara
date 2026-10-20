<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de espera do transbordo e disponibilidade do time.
 *
 * Em produção, o transbordo para a Secretaria e o Financeiro volta para O
 * Lara quando ninguém do time está disponível (almoço, abertura, fim do dia).
 * Agora a conversa fica numa fila: O Lara tenta de novo, em silêncio, a cada
 * poucos minutos, até alguém pegar ou o horário de atendimento acabar.
 *
 * bot_sessions:
 *   - handoff_team_uuid: o time do último transbordo (é para ele que a fila
 *     tenta de novo);
 *   - queued_at / queued_until / queue_next_at / queue_attempts: a fila.
 *
 * bot_attendance_events (só das enviadas): quem estava no time no momento do
 * distribute, com o status de cada um na Poli — guardado para sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->string('handoff_team_uuid')->nullable()->after('ending_reason');
            $table->timestamp('queued_at')->nullable()->after('handoff_team_uuid');
            $table->timestamp('queued_until')->nullable()->after('queued_at');
            $table->timestamp('queue_next_at')->nullable()->after('queued_until');
            $table->unsignedSmallInteger('queue_attempts')->default(0)->after('queue_next_at');

            $table->index(['state', 'queue_next_at'], 'bot_sessions_fila_idx');
        });

        Schema::table('bot_attendance_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('team_size')->nullable()->after('target');
            $table->unsignedSmallInteger('team_online')->nullable()->after('team_size');
            $table->unsignedSmallInteger('team_available')->nullable()->after('team_online');
            $table->json('team_snapshot')->nullable()->after('team_available');
        });
    }

    public function down(): void
    {
        Schema::table('bot_attendance_events', function (Blueprint $table) {
            $table->dropColumn(['team_size', 'team_online', 'team_available', 'team_snapshot']);
        });

        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropIndex('bot_sessions_fila_idx');
            $table->dropColumn(['handoff_team_uuid', 'queued_at', 'queued_until', 'queue_next_at', 'queue_attempts']);
        });
    }
};
