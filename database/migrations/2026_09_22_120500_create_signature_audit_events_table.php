<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trilha de auditoria do módulo: tabela SOMENTE INSERÇÃO.
 *
 * Três camadas sustentam isso, e nenhuma delas sozinha basta:
 *
 *  1. O model recusa update e delete (App\Models\SignatureAuditEvent).
 *  2. Cada linha guarda o hash da anterior DAQUELE DOCUMENTO, encadeando-as:
 *     alterar uma linha no banco, por fora da aplicação, quebra a conferência
 *     de todas as seguintes e fica detectável.
 *  3. Em produção, o usuário do banco não deve ter UPDATE nem DELETE nesta
 *     tabela. Isso é um GRANT, não uma migration: a migration rodaria com uma
 *     credencial que pode não ter privilégio para concedê-lo, e o SQLite da
 *     suíte não reproduz permissão de tabela. Está documentado no README do
 *     módulo.
 *
 * A cadeia é POR DOCUMENTO, e não global: conferir um documento não obriga a
 * varrer a tabela inteira, e duas assinaturas simultâneas em documentos
 * diferentes não disputam a mesma ponta da cadeia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_audit_events', function (Blueprint $table) {
            $table->id();

            /*
             | Sem foreign key, de propósito — ao contrário das outras tabelas
             | do módulo. Um `cascadeOnDelete` levaria a auditoria junto com o
             | documento, que é exatamente o que ela existe para impedir.
             */
            $table->unsignedBigInteger('signature_document_id')->index();
            $table->unsignedBigInteger('signature_signer_id')->nullable()->index();
            $table->unsignedBigInteger('signature_request_id')->nullable()->index();

            $table->string('event', 40)->index();

            // Contexto do evento. CPF entra MASCARADO (ver App\Support\Cpf);
            // token, em nenhuma hipótese.
            $table->json('payload')->nullable();

            $table->enum('actor_type', ['user', 'kiosk', 'system'])->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Hora do servidor, sempre.
            $table->timestamp('occurred_at')->index();

            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64);

            /*
             | Só `created_at`: `updated_at` não faria sentido numa tabela que
             | nunca é atualizada (o model fixa UPDATED_AT = null).
             */
            $table->timestamp('created_at')->nullable();

            // A ponta da cadeia de um documento, e a leitura em ordem.
            $table->index(['signature_document_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_audit_events');
    }
};
