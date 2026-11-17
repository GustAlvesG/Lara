<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões NEGADAS a um usuário (desde 17/11/2026).
 *
 * A negação vence tudo: o setor, a permissão individual e até o acesso total.
 * É o jeito de tirar de uma pessoa algo que o setor dela dá sem tirar a
 * pessoa do setor. Quem lê é o App\Authorization\AccessResolver; quem grava é
 * o App\Authorization\AccessManager.
 *
 * Tabela própria, e não uma coluna na `model_has_permissions` do Spatie: o
 * Spatie leria a linha como concessão.
 *
 * Idempotente: o deploy pode rodar de novo sem quebrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_permission_denials')) {
            return;
        }

        Schema::create('user_permission_denials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            // Nome curto: o automático passa de 64 caracteres no MySQL.
            $table->unique(['user_id', 'permission_id'], 'upd_user_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_denials');
    }
};
