<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convite por e-mail para assinar pelo gov.br: para quem, quando e por
     * quem o PDF foi enviado. Sem link nem token — a devolução passa pelo
     * atendente.
     */
    public function up(): void
    {
        Schema::create('signature_govbr_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_signer_id')
                ->constrained('signature_signers')
                ->cascadeOnDelete();
            $table->string('email', 191);
            // Sem foreign key para `users` — ver `signature_templates`.
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('sent_by_name', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_govbr_invites');
    }
};
