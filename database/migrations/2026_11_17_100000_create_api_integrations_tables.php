<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gestão de tokens da API (Administração › Integrações).
     *
     * api_integrations        — o sistema externo (site Next.js, HAL, portaria…).
     * api_integration_tokens  — um token por INSTALAÇÃO (site produção ≠ site
     *                           homologação): só o SHA-256 fica no banco; o texto
     *                           aparece uma vez, na geração. Revogado não é
     *                           apagado (fica para a auditoria).
     * api_token_usage_daily   — contagem de chamadas por dia, token, escopo e IP,
     *                           inclusive do token antigo do .env ("legado"), das
     *                           chamadas sem token e das recusadas.
     *
     * Sem chave estrangeira para `users`: o User está na conexão `mysql` e os
     * testes rodam em SQLite. Nomes curtos de índice/FK (limite de 64 do
     * MySQL). Idempotente: se parar no meio, rodar de novo completa.
     */
    public function up(): void
    {
        if (! Schema::hasTable('api_integrations')) {
            Schema::create('api_integrations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique('api_int_name_uq');
                $table->string('system', 40);
                $table->text('notes')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_integration_tokens')) {
            Schema::create('api_integration_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('api_integration_id');
                $table->string('name', 120);
                $table->char('token_hash', 64)->unique('api_int_tok_hash_uq');
                $table->string('token_hint', 12);
                $table->json('scopes');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->string('last_used_route', 190)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->string('revoke_reason', 255)->nullable();
                $table->unsignedBigInteger('replaced_by_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('api_integration_id', 'api_int_tok_int_idx');
                $table->foreign('api_integration_id', 'api_int_tok_int_fk')
                    ->references('id')->on('api_integrations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('api_token_usage_daily')) {
            Schema::create('api_token_usage_daily', function (Blueprint $table) {
                $table->id();
                $table->date('day');
                // 0 = sem token gerenciado (legado, sem token, recusado).
                $table->unsignedBigInteger('api_integration_token_id')->default(0);
                // token | legado | sem-token | recusado
                $table->string('kind', 12);
                $table->string('scope', 40);
                $table->string('ip', 45);
                $table->unsignedInteger('hits')->default(0);
                $table->string('last_route', 190)->nullable();
                $table->timestamp('last_seen_at')->nullable();

                $table->unique(['day', 'api_integration_token_id', 'kind', 'scope', 'ip'], 'api_tok_usage_uq');
                $table->index(['kind', 'day'], 'api_tok_usage_kind_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_usage_daily');
        Schema::dropIfExists('api_integration_tokens');
        Schema::dropIfExists('api_integrations');
    }
};
