<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dono dos tokens Sanctum do sistema de captura.
     *
     * Mesmo arranjo do `placar_api_clientes`: não é usuário do sistema, não
     * faz login, não tem papel nem permissão — existe para o token ter a quem
     * pertencer e para cada integração ter nome próprio, revogável sozinha.
     *
     * Tabela separada da do Placar de propósito: são integrações diferentes,
     * com abilities diferentes, e um vazamento de uma não pode virar acesso à
     * outra.
     */
    public function up(): void
    {
        Schema::create('replay_api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_api_clients');
    }
};
