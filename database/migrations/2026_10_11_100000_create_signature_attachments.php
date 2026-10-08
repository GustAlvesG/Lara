<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos do documento: identidade, comprovante, o que o documento exigir.
     *
     * O que se pede vem de dois lugares, como as perguntas: do MODELO
     * (`signature_templates.attachments`, versionado com ele) e do próprio
     * DOCUMENTO (`signature_documents.attachment_requirements`). Cada item
     * tem rótulo e diz se é obrigatório; o obrigatório segura a conclusão
     * (o documento assinado só finaliza com ele).
     *
     * Os arquivos ficam no disco privado do módulo, com o hash de cada um —
     * é o hash que o manifesto imprime.
     *
     * Cada passo confere se já foi feito: a primeira versão desta migration
     * parou no meio na homologação (nome de índice acima de 64 caracteres, e o
     * MySQL não desfaz DDL), e ela precisa terminar de onde parou.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('signature_templates', 'attachments')) {
            Schema::table('signature_templates', function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('parties');
            });
        }

        if (!Schema::hasColumn('signature_documents', 'attachment_requirements')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->json('attachment_requirements')->nullable()->after('data');
            });
        }

        if (Schema::hasTable('signature_attachments')) {
            if (!Schema::hasIndex('signature_attachments', 'sig_attachments_doc_key_idx')) {
                Schema::table('signature_attachments', function (Blueprint $table) {
                    $table->index(['signature_document_id', 'requirement_key'], 'sig_attachments_doc_key_idx');
                });
            }

            return;
        }

        Schema::create('signature_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_document_id')
                ->constrained('signature_documents')
                ->cascadeOnDelete();
            // O item pedido (do modelo ou do documento). Nulo = anexo avulso.
            $table->string('requirement_key', 60)->nullable();
            // Retrato do rótulo no envio: o item pode mudar de nome depois.
            $table->string('label', 120);
            $table->string('original_name', 191);
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            // Sem foreign key para `users` — ver `signature_templates`.
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_name', 150)->nullable();
            $table->timestamps();

            // Nome curto: o automático passa dos 64 caracteres do MySQL.
            $table->index(['signature_document_id', 'requirement_key'], 'sig_attachments_doc_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_attachments');

        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn('attachment_requirements');
        });

        Schema::table('signature_templates', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
