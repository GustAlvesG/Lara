<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O documento propriamente dito: um modelo preenchido com os dados de quem vai
 * assinar, no atendimento.
 *
 * Duas colunas sustentam o valor probatório de tudo o que vem depois:
 *
 *  - `body_snapshot` guarda o HTML exato do documento no momento do
 *    congelamento. O PDF final é re-renderizado dele, e não do modelo vivo:
 *    sem isso, uma revisão do modelo mudaria o PDF entregue de um documento
 *    já assinado.
 *  - `original_sha256` é o hash do PDF que o tablet exibiu. É o que a página
 *    pública de validação confere e o que o manifesto cita.
 *
 * A partir de `frozen_at`, os dados e o texto não mudam mais — o que é
 * garantido pela aplicação (SignatureDocumentService), não por trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_documents', function (Blueprint $table) {
            $table->id();

            // Aponta para a LINHA da versão usada, não para o modelo: é o que
            // faz o documento continuar imprimindo o texto que foi lido.
            $table->foreignId('signature_template_id')->constrained('signature_templates');

            // Desnormalizada para a varredura "quais documentos estão na
            // versão antiga?" não precisar de join.
            $table->unsignedInteger('template_version');

            $table->string('title', 200);

            // Valores preenchidos para as variáveis do modelo.
            $table->json('data')->nullable();

            $table->longText('body_snapshot')->nullable();

            $table->enum('status', [
                'draft',
                'awaiting_signature',
                'signed',
                'finalized',
                'refused',
                'canceled',
                'expired',
            ])->default('draft')->index();

            // Disco privado (`local`), servido só por rota autenticada.
            $table->string('original_path', 255)->nullable();
            $table->char('original_sha256', 64)->nullable()->index();
            $table->timestamp('frozen_at')->nullable();

            $table->string('final_path', 255)->nullable();
            $table->char('final_sha256', 64)->nullable()->index();
            $table->timestamp('finalized_at')->nullable();

            /*
             | Código público da página de validação (/validar/{codigo}).
             | Não é o id: um id sequencial deixaria adivinhar o documento do
             | vizinho na fila do balcão.
             */
            $table->string('validation_code', 24)->nullable()->unique();

            // Posto de atendimento onde a assinatura aconteceu. Vai ao
            // manifesto.
            $table->string('location', 120)->nullable();

            // Autor (atendente). Sem foreign key para `users` — ver
            // `signature_templates`.
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->string('canceled_reason', 255)->nullable();

            // Prazo do documento inteiro: passou daqui sem assinatura, o
            // comando `signature:expire` o encerra.
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_documents');
    }
};
