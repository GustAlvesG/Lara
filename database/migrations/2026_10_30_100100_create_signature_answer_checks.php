<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência das respostas do tablet pelo atendente.
 *
 * Documento com pergunta a quem assina, liberado por QR no balcão, só é
 * assinado depois de o atendente conferir as respostas. Cada linha é uma ação
 * dele: aprovou, devolveu (com observação) ou corrigiu. A aprovação vale para
 * o `document_sha256` dela: se as respostas mudarem depois, o hash do
 * original muda e a aprovação deixa de valer sozinha.
 *
 * Só inserção, como a trilha. `user_name` é retrato (sem FK para `users`,
 * que vive noutra conexão). `field_keys`: as chaves corrigidas — nunca os
 * valores. Ver App\Services\Signature\SignatureAnswerCheckService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_answer_checks')) {
            return;
        }

        Schema::create('signature_answer_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_document_id')
                ->constrained('signature_documents', 'id', 'sig_answer_checks_doc_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('signature_request_id')->nullable();
            $table->string('action', 20);
            $table->char('document_sha256', 64)->nullable();
            $table->text('note')->nullable();
            $table->json('field_keys')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index(['signature_document_id', 'id'], 'sig_answer_checks_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_answer_checks');
    }
};
