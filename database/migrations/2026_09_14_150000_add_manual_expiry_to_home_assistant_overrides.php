<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comando manual vindo de fora (Telegram → Home Assistant → Lara).
 *
 * Três mudanças, todas por causa do comando externo:
 *
 * 1. `expires_at`: a vigência até aqui era só por data (`start_date`/`end_date`),
 *    então uma ação rápida valia até a meia-noite. Um comando com duração
 *    ("liga por 2 horas") precisa de hora, e quem decide isso é
 *    HomeAssistantOverride::stateAt().
 * 2. `origin`: de onde veio o comando, em texto livre ("Telegram (Gustavo)").
 *    O painel continua identificando o autor por `created_by`.
 * 3. `created_by` passa a aceitar nulo: a API é autenticada por token de
 *    integração, sem usuário de sistema por trás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->after('end_date');
            $table->string('origin', 80)->nullable()->after('created_by');
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Comandos sem autor impedem a volta da coluna para NOT NULL.
        Schema::table('home_assistant_overrides', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'origin']);
        });
    }
};
