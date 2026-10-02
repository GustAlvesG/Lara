<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de quem mexeu em acesso: vínculo com setor, permissão de setor,
 * permissão individual, acesso total e usuário criado.
 *
 * Existe porque o coordenador passa a conceder acesso sozinho (colocar
 * alguém no setor dá a essa pessoa tudo o que o setor alcança). Sem o
 * registro, "quem deu acesso a fulano?" não tem resposta.
 *
 * Sem chave estrangeira de propósito: o registro tem que sobreviver ao
 * usuário e ao setor que ele descreve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 60);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('sector_id')->nullable()->index();
            $table->string('permission')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_logs');
    }
};
