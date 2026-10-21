<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura remota: o link enviado por e-mail que abre a tela de quem assina
 * no site externo (Next.js, na DMZ), sem login.
 *
 * - `signature_remote_links`: um link por envio. O token em claro vai só no
 *   e-mail; o banco guarda o sha256. Vale por horas (`expires_at`) e pode ser
 *   aberto várias vezes até a pessoa assinar — cada abertura vira uma
 *   liberação (`signature_requests`) com sessão curta, como a do tablet. As
 *   tentativas de CPF contam no LINK, somadas entre as aberturas: abrir de
 *   novo não devolve tentativa.
 * - `signature_requests.signature_remote_link_id`: de qual link nasceu a
 *   liberação. Nulo = tablet, como sempre foi.
 * - `signature_attachments.uploaded_by_signer_id`: anexo enviado pelo próprio
 *   signatário na tela do link.
 *
 * Índices com nome curto e passos que conferem antes de agir: o nome
 * automático passa dos 64 caracteres do MySQL (o SQLite dos testes não
 * acusa), e uma DDL que para no meio não volta atrás.
 *
 * Sem foreign key para `users`, como no resto do módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_remote_links')) {
            Schema::create('signature_remote_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('signature_signer_id');
                $table->char('token_hash', 64);
                $table->string('email', 191);
                $table->string('status', 20)->default('active');
                $table->timestamp('expires_at');
                $table->unsignedTinyInteger('identity_attempts')->default(0);
                $table->unsignedInteger('opened_count')->default(0);
                $table->timestamp('last_opened_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->string('sent_by_name', 150)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->string('revoked_by_name', 150)->nullable();
                $table->timestamps();
            });
        }

        $indices = array_column(Schema::getIndexes('signature_remote_links'), 'name');

        Schema::table('signature_remote_links', function (Blueprint $table) use ($indices) {
            if (!in_array('sig_remote_links_token_uq', $indices, true)) {
                $table->unique('token_hash', 'sig_remote_links_token_uq');
            }

            if (!in_array('sig_remote_links_signer_idx', $indices, true)) {
                $table->index(['signature_signer_id', 'status'], 'sig_remote_links_signer_idx');
            }
        });

        $fks = array_column(Schema::getForeignKeys('signature_remote_links'), 'name');

        if (!in_array('sig_remote_links_signer_fk', $fks, true)) {
            Schema::table('signature_remote_links', function (Blueprint $table) {
                $table->foreign('signature_signer_id', 'sig_remote_links_signer_fk')
                    ->references('id')
                    ->on('signature_signers')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('signature_requests', 'signature_remote_link_id')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('signature_remote_link_id')->nullable()->after('identity_method');
                $table->index('signature_remote_link_id', 'sig_requests_remote_link_idx');
            });
        }

        /*
         | Anexo enviado pelo PRÓPRIO signatário, pela tela do link remoto. Nulo
         | = enviado pelo atendente (`uploaded_by`), como sempre foi. É o que
         | deixa a pessoa trocar ou apagar o que ela mesma enviou, até assinar.
         */
        if (!Schema::hasColumn('signature_attachments', 'uploaded_by_signer_id')) {
            Schema::table('signature_attachments', function (Blueprint $table) {
                $table->unsignedBigInteger('uploaded_by_signer_id')->nullable()->after('uploaded_by_name');
                $table->index('uploaded_by_signer_id', 'sig_attachments_signer_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_attachments', 'uploaded_by_signer_id')) {
            Schema::table('signature_attachments', function (Blueprint $table) {
                $table->dropIndex('sig_attachments_signer_idx');
                $table->dropColumn('uploaded_by_signer_id');
            });
        }

        if (Schema::hasColumn('signature_requests', 'signature_remote_link_id')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->dropIndex('sig_requests_remote_link_idx');
                $table->dropColumn('signature_remote_link_id');
            });
        }

        // A FK cai junto com a tabela.
        Schema::dropIfExists('signature_remote_links');
    }
};
