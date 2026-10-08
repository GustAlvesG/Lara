<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada PDF assinado pelo gov.br que o atendente enviou para conferência,
     * aprovado ou recusado, com o resultado.
     *
     * Guarda também os recusados: é o registro do que chegou de volta e por
     * que não serviu — e é o que explica, depois, um documento que "foi
     * assinado mas não valeu".
     *
     * `result` é o GovbrValidationResult inteiro (as conferências e as
     * assinaturas), com CPF sempre mascarado. O arquivo fica no disco privado
     * do módulo (`file_path`), nunca em URL.
     *
     * `checked_by` sem foreign key para `users` — ver `signature_templates`;
     * o nome vai em `checked_by_name`, como retrato, pelo mesmo motivo de
     * `add_attendant_name_to_signature_documents`.
     */
    public function up(): void
    {
        Schema::create('signature_govbr_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_document_id')
                ->constrained('signature_documents')
                ->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->char('file_sha256', 64);
            $table->unsignedInteger('file_bytes');
            $table->boolean('valid')->default(false);
            // Sobre qual PDF do Lara a assinatura foi feita: 'original' ou 'final'.
            $table->string('base', 16)->nullable();
            $table->json('result');
            // O que a conferência fez no documento: quem ela deu como assinado,
            // se fechou o documento, ou por que não concluiu nada.
            $table->json('conclusion')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->string('checked_by_name', 150)->nullable();
            $table->timestamps();

            $table->index(['signature_document_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_govbr_checks');
    }
};
