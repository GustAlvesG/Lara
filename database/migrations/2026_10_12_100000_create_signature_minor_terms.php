<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termo de Menores — autorização de entrada de menor em evento, assinada pelo
 * próprio sócio no tablet de autoatendimento.
 *
 *  - `signature_minor_terms`: o termo de um evento — o modelo do documento e o
 *    período em que o tablet aceita termos novos. Um vigente por vez.
 *  - `signature_minor_authorizations`: um documento por menor. Liga o
 *    documento (que é um SignatureDocument comum, com manifesto, trilha e
 *    validação) ao termo e às pessoas do MultiClubes. É por ela que o tablet
 *    sabe que o menor "já está autorizado" e que a tela de histórico lista.
 *  - `signature_kiosk_devices`: o tablet pareado por QR Code por um usuário com
 *    permissão. Sem pareamento vivo, o autoatendimento não responde.
 *
 * Os ids de pessoa (`*_member_id`) são do MultiClubes (`dbo.Members.Id`), não
 * da tabela local `members`. Nomes são retrato do dia, como no resto do módulo.
 * Sem FK para `users` (o model User fixa a conexão `mysql`).
 *
 * Retomável: cada tabela confere se já existe (MySQL não desfaz DDL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_minor_terms')) {
            Schema::create('signature_minor_terms', function (Blueprint $table) {
                $table->id();
                // Nome do evento, como aparece no tablet e no histórico.
                $table->string('name', 150);
                // O modelo (a raiz das versões): o documento sai da versão
                // vigente no momento da assinatura.
                $table->foreignId('signature_template_id')->constrained('signature_templates');
                // Período em que o tablet aceita termos novos (dias inteiros).
                $table->date('starts_on');
                $table->date('ends_on');
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->timestamps();

                $table->index(['active', 'starts_on', 'ends_on'], 'sig_minor_terms_period_idx');
            });
        }

        if (!Schema::hasTable('signature_kiosk_devices')) {
            Schema::create('signature_kiosk_devices', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->nullable();
                // QR de pareamento (e código digitado, no modo sem HTTPS):
                // só o hash, uso único, vida curta.
                $table->char('pairing_token_hash', 64)->nullable()->unique();
                $table->char('pairing_code_hash', 64)->nullable()->index();
                $table->timestamp('pairing_expires_at')->nullable();
                // O segredo do cookie do tablet pareado: só o hash.
                $table->char('token_hash', 64)->nullable()->unique();
                $table->timestamp('paired_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->unsignedBigInteger('paired_by')->nullable();
                $table->string('paired_by_name', 150)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('revoked_by_name', 150)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('signature_minor_authorizations')) {
            Schema::create('signature_minor_authorizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signature_minor_term_id')->constrained('signature_minor_terms');
                $table->foreignId('signature_document_id')->unique()->constrained('signature_documents');
                $table->unsignedBigInteger('signature_kiosk_device_id')->nullable();
                $table->string('title_code', 20);
                $table->unsignedBigInteger('minor_member_id');
                $table->string('minor_name', 150);
                $table->date('minor_birth_date');
                $table->unsignedBigInteger('responsible_member_id');
                $table->string('responsible_name', 150);
                $table->timestamps();

                // Nome curto: o automático passa de 64 caracteres no MySQL.
                $table->index(['signature_minor_term_id', 'minor_member_id'], 'sig_minor_auth_term_minor_idx');
                $table->index('title_code', 'sig_minor_auth_title_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_minor_authorizations');
        Schema::dropIfExists('signature_kiosk_devices');
        Schema::dropIfExists('signature_minor_terms');
    }
};
