<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As evidências que sustentam a assinatura eletrônica AVANÇADA (Lei
 * 14.063/2020): o que liga o ato àquela pessoa e àquele conteúdo.
 *
 * Uma linha por signatário, gravada na MESMA transação da assinatura — não
 * existe assinatura sem evidência, nem evidência sem assinatura.
 *
 * Arquivos (PNG do traço e foto) ficam no disco privado e só saem por rota
 * autenticada, com permissão própria para a foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_evidences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('signature_signer_id')
                ->constrained('signature_signers')
                ->cascadeOnDelete();

            $table->string('signature_path', 255);

            /*
             | Os traços em vetor: pontos com x, y, pressão e tempo relativo.
             | Valem mais que o PNG como prova — mostram o movimento da mão, e
             | uma imagem colada não tem nenhum.
             */
            $table->longText('strokes')->nullable();

            $table->string('photo_path', 255)->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Quanto tempo o documento ficou na tela até liberar o botão.
            $table->unsignedInteger('read_seconds')->nullable();
            $table->boolean('scrolled_to_end')->default(false);
            $table->boolean('accepted')->default(false);

            // Tamanho da tela e orientação no momento da assinatura: ajuda a
            // responder "o que cabia na tela quando ele leu".
            $table->json('viewport')->nullable();

            // Hora do SERVIDOR. O relógio do tablet não entra em nada.
            $table->timestamp('server_signed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_evidences');
    }
};
