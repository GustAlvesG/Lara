<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disparo de E-mails (docs/funcionalidades/disparo-emails.md).
 *
 * `email_campaigns`: o e-mail (assunto, remetente, HTML) e a situação do
 * disparo. `email_campaign_recipients`: uma linha por destinatário, com as
 * colunas da planilha em `data` (viram as variáveis {{coluna}}) e o desfecho
 * do envio. `email_suppressions`: quem pediu para não receber; o envio pula.
 *
 * `created_by` sem FK: `users` vive noutra conexão. Nomes de índice curtos
 * (limite de 64 do MySQL) e a migration idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('subject');
                $table->string('from_address');
                $table->string('from_name')->nullable();
                $table->string('reply_to')->nullable();
                $table->longText('html')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                // Colunas da última lista importada: slug => rótulo do cabeçalho.
                $table->json('columns')->nullable();
                $table->string('list_name')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_at'], 'email_campaigns_status_idx');
            });
        }

        if (! Schema::hasTable('email_campaign_recipients')) {
            Schema::create('email_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_campaign_id')
                    ->constrained('email_campaigns', 'id', 'ecr_campaign_fk')
                    ->cascadeOnDelete();
                $table->string('email');
                $table->string('name')->nullable();
                $table->json('data')->nullable();
                $table->string('status', 10)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['email_campaign_id', 'email'], 'ecr_campaign_email_unique');
                $table->index(['email_campaign_id', 'status', 'id'], 'ecr_campaign_status_idx');
            });
        }

        if (! Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique('email_suppressions_email_unique');
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_suppressions');
    }
};
