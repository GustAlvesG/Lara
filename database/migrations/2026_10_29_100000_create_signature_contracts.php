<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos (Assinaturas → Contratos): o contrato é redigido em cláusulas,
 * validado pela Gerência e, aprovado, vira um documento de assinatura em
 * rascunho. Ver docs/funcionalidades/contratos.md.
 *
 * Nenhuma tabela tem FK para `users` (o model User é preso à conexão `mysql`)
 * nem para `sectors`: o vínculo é o id solto mais o nome em retrato, como no
 * resto do módulo.
 *
 * Índices com nome curto: o nome automático passa de 64 caracteres no MySQL —
 * o SQLite da suíte não acusa. Idempotente (hasTable): um deploy que caia no
 * meio pode rodar de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_contracts')) {
            Schema::create('signature_contracts', function (Blueprint $table) {
                $table->id();
                // Setor dono: só ele (e a Gerência) vê o contrato.
                $table->unsignedBigInteger('sector_id')->nullable()->index('sig_contracts_sector_idx');
                $table->string('sector_name', 120)->nullable();
                // contract | addendum (aditivo de um contrato aprovado).
                $table->string('kind', 10)->default('contract');
                $table->foreignId('parent_id')->nullable()
                    ->constrained('signature_contracts', 'id', 'sig_contracts_parent_fk')->nullOnDelete();
                $table->unsignedSmallInteger('addendum_seq')->nullable();
                // "CTL - 12/26": sequência única para todos os setores, por ano,
                // dada na aprovação. O aditivo repete o número do contrato + "A1".
                $table->unsignedInteger('number_seq')->nullable();
                $table->unsignedSmallInteger('number_year')->nullable();
                $table->string('number', 40)->nullable()->unique('sig_contracts_number_uq');
                $table->string('title', 200);
                $table->string('status', 20)->default('draft')->index('sig_contracts_status_idx');
                // A cópia de trabalho: partes, variáveis, cláusulas, signatários.
                $table->json('content')->nullable();
                $table->unsignedInteger('current_version')->default(0);
                $table->unsignedBigInteger('approved_version_id')->nullable();
                $table->foreignId('signature_document_id')->nullable()
                    ->constrained('signature_documents', 'id', 'sig_contracts_document_fk')->nullOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 120)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('approved_by_name', 120)->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamps();

                $table->unique(['number_year', 'number_seq'], 'sig_contracts_seq_uq');
            });
        }

        if (!Schema::hasTable('signature_contract_versions')) {
            Schema::create('signature_contract_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_contract_id')
                    ->constrained('signature_contracts', 'id', 'sig_contract_versions_contract_fk')->cascadeOnDelete();
                $table->unsignedInteger('version');
                // Retrato do que foi enviado à Gerência: nunca muda.
                $table->json('content');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->string('submitted_by_name', 120)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->text('note')->nullable();
                // A versão nasceu de ajuste feito pela própria Gerência na validação.
                $table->boolean('by_reviewer')->default(false);
                // approved | returned | null (ainda em validação, ou substituída).
                $table->string('decision', 20)->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->string('decided_by_name', 120)->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();

                $table->unique(['signature_contract_id', 'version'], 'sig_contract_versions_uq');
            });
        }

        if (!Schema::hasTable('signature_contract_comments')) {
            Schema::create('signature_contract_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_contract_id')
                    ->constrained('signature_contracts', 'id', 'sig_contract_comments_contract_fk')->cascadeOnDelete();
                $table->foreignId('signature_contract_version_id')->nullable()
                    ->constrained('signature_contract_versions', 'id', 'sig_contract_comments_version_fk')->cascadeOnDelete();
                // Cláusula comentada (uid da cláusula); nulo = observação geral.
                $table->string('clause_uid', 40)->nullable();
                $table->text('body');
                $table->unsignedBigInteger('author_id')->nullable();
                $table->string('author_name', 120)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolved_by_name', 120)->nullable();
                $table->timestamps();

                $table->index(['signature_contract_id', 'resolved_at'], 'sig_contract_comments_open_idx');
            });
        }

        if (!Schema::hasTable('signature_contract_events')) {
            Schema::create('signature_contract_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_contract_id')
                    ->constrained('signature_contracts', 'id', 'sig_contract_events_contract_fk')->cascadeOnDelete();
                $table->string('event', 40);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 120)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('signature_contract_attachments')) {
            Schema::create('signature_contract_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_contract_id')
                    ->constrained('signature_contracts', 'id', 'sig_contract_attach_contract_fk')->cascadeOnDelete();
                $table->string('label', 120);
                $table->string('original_name', 191);
                $table->string('path');
                $table->string('mime', 60);
                $table->unsignedInteger('bytes');
                $table->char('sha256', 64);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->string('uploaded_by_name', 120)->nullable();
                $table->timestamps();
            });
        }

        // Biblioteca de cláusulas, por setor.
        if (!Schema::hasTable('signature_contract_clauses')) {
            Schema::create('signature_contract_clauses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sector_id')->nullable()->index('sig_contract_clauses_sector_idx');
                $table->string('sector_name', 120)->nullable();
                $table->string('title', 200);
                $table->string('category', 80)->nullable();
                // Os itens da cláusula (texto e nível), como no contrato.
                $table->json('items');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 120)->nullable();
                $table->string('updated_by_name', 120)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Modelos de contrato completos, por setor.
        if (!Schema::hasTable('signature_contract_models')) {
            Schema::create('signature_contract_models', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sector_id')->nullable()->index('sig_contract_models_sector_idx');
                $table->string('sector_name', 120)->nullable();
                $table->string('name', 150);
                $table->json('content');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 120)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Cadastro de contratadas e fiadores, para reaproveitar no próximo contrato.
        if (!Schema::hasTable('signature_contract_parties')) {
            Schema::create('signature_contract_parties', function (Blueprint $table) {
                $table->id();
                // pj | pf
                $table->string('kind', 2);
                // CNPJ ou CPF, só os caracteres (o CNPJ alfanumérico tem letras).
                $table->string('document', 20)->unique('sig_contract_parties_doc_uq');
                $table->string('name', 200);
                $table->json('data')->nullable();
                $table->string('updated_by_name', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_contract_parties');
        Schema::dropIfExists('signature_contract_models');
        Schema::dropIfExists('signature_contract_clauses');
        Schema::dropIfExists('signature_contract_attachments');
        Schema::dropIfExists('signature_contract_events');
        Schema::dropIfExists('signature_contract_comments');
        Schema::dropIfExists('signature_contract_versions');
        Schema::dropIfExists('signature_contracts');
    }
};
