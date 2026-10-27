<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisões automáticas de contrato pelo Claude (Assinaturas → Contratos):
 * a Gerência pede, o Lara manda as cláusulas e guarda o resumo das
 * inconsistências com as sugestões. Ver docs/funcionalidades/contratos.md.
 *
 * Sem FK para `users` (User é preso à conexão `mysql`); índices com nome
 * curto (limite de 64 no MySQL); idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contract_ai_reviews')) {
            return;
        }

        Schema::create('signature_contract_ai_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_contract_id')
                ->constrained('signature_contracts', 'id', 'sig_contract_ai_contract_fk')->cascadeOnDelete();
            // sha256 do texto enviado: mesmo texto, mesma revisão (não paga duas vezes).
            $table->char('content_hash', 64);
            $table->string('model', 60);
            // sem_ressalvas | ajustes_recomendados | revisar_antes_de_aprovar
            $table->string('verdict', 30);
            $table->text('summary');
            // [{clausula, trecho, gravidade, problema, sugestao}]
            $table->json('findings');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name', 120)->nullable();
            $table->timestamps();

            $table->index(['signature_contract_id', 'content_hash'], 'sig_contract_ai_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_contract_ai_reviews');
    }
};
