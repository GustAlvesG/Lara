<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Lara passa a conduzir as conversas atribuídas ao usuário O Lara na Poli
 * (Implementação 2). A sessão precisa lembrar três coisas novas:
 *
 *   - se a conversa é do O Lara (a Lara fala) ou só comparação em sombra de
 *     uma conversa que continua no bot da Poli;
 *   - o prazo do encerramento diferido (estado `ending`);
 *   - qual atendimento a própria Lara encerrou, para resgatar a mensagem que
 *     a Poli prende nele logo depois do close.
 *
 * A inatividade continua contada por `last_interaction_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->boolean('lara_owned')->default(false)->after('state');
            $table->timestamp('ending_at')->nullable()->after('human_since');
            $table->string('closed_attendance_uuid')->nullable()->after('attendance_uuid');
            $table->timestamp('closed_at')->nullable()->after('closed_attendance_uuid');

            $table->index(['state', 'lara_owned'], 'bot_sessions_estado_dono_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bot_sessions', function (Blueprint $table) {
            $table->dropIndex('bot_sessions_estado_dono_idx');
            $table->dropColumn(['lara_owned', 'ending_at', 'closed_attendance_uuid', 'closed_at']);
        });
    }
};
