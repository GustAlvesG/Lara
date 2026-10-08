<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão interna do documento assinado.
 *
 * Todo documento concluído passa por uma revisão: outra pessoa, que não
 * acompanhou a assinatura, confere se os processos internos daquele
 * atendimento foram feitos (os itens vêm do modelo, `review_items`). Fica
 * disponível no dia seguinte à conclusão; quem tem a permissão de coordenação
 * revisa antes e revisa os próprios.
 *
 * `signature_reviews` guarda cada revisão (há nova revisão quando a anterior
 * deixou pendência); `review_status` no documento é o retrato da última, para
 * a fila consultar sem juntar tabelas.
 *
 * Retomável: cada passo confere se já foi feito (MySQL não desfaz DDL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_templates', 'review_items')) {
            Schema::table('signature_templates', function (Blueprint $table) {
                $table->json('review_items')->nullable()->after('attachments');
            });
        }

        if (!Schema::hasColumn('signature_documents', 'review_status')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                // null = sem revisão ainda (ou não concluído); ok; issues.
                $table->string('review_status', 20)->nullable()->after('finalized_at');
                $table->timestamp('reviewed_at')->nullable()->after('review_status');
                $table->index(['status', 'review_status'], 'sig_documents_review_idx');
            });
        }

        if (!Schema::hasTable('signature_reviews')) {
            Schema::create('signature_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_document_id')->constrained('signature_documents')->cascadeOnDelete();
                // ok | issues
                $table->string('result', 20);
                // Os itens do modelo no momento da revisão, cada um com "feito".
                $table->json('items')->nullable();
                $table->text('notes')->nullable();
                // Antes do dia seguinte ou de documento que a pessoa acompanhou:
                // só com a permissão de coordenação, e fica dito.
                $table->boolean('early')->default(false);
                $table->boolean('own')->default(false);
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->string('reviewed_by_name', 150)->nullable();
                $table->timestamps();

                $table->index(['signature_document_id', 'id'], 'sig_reviews_doc_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_reviews');

        if (Schema::hasColumn('signature_documents', 'review_status')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropIndex('sig_documents_review_idx');
                $table->dropColumn(['review_status', 'reviewed_at']);
            });
        }

        if (Schema::hasColumn('signature_templates', 'review_items')) {
            Schema::table('signature_templates', function (Blueprint $table) {
                $table->dropColumn('review_items');
            });
        }
    }
};
