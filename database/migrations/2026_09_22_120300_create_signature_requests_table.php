<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A liberação de UM documento para UM tablet, pelo QR Code.
 *
 * O tablet não é pareado: não guarda token de longa duração nem sabe qual é o
 * próximo documento. Cada assinatura exige que o atendente gere um QR novo, e
 * a leitura desse QR é o único jeito de o tablet chegar ao documento.
 *
 * O token NUNCA é gravado em claro — só o sha256. Quem tiver acesso de leitura
 * ao banco não consegue abrir uma sessão de assinatura com o que está aqui.
 *
 * Dois prazos diferentes, de propósito:
 *
 *  - `expires_at` é curto (5 min): é o tempo entre o atendente mostrar o QR e
 *    o tablet lê-lo, e um QR que fica valendo é um QR que alguém fotografa.
 *  - `session_expires_at` começa na leitura (15 min): é o tempo do
 *    atendimento em si — ler o documento, conferir o CPF, assinar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('signature_signer_id')
                ->constrained('signature_signers')
                ->cascadeOnDelete();

            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            // Consumo: a primeira leitura válida. A segunda é recusada e
            // auditada (qr_reuse_blocked).
            $table->timestamp('consumed_at')->nullable();

            /*
             | Sessão do tablet. Guarda o sha256 do valor do cookie `lara_sign`
             | — cookie próprio, e não a sessão web do app, para poder ser
             | SameSite=Strict sem mexer na configuração de sessão do sistema
             | inteiro, e para o cancelamento pelo painel matar a sessão na
             | requisição seguinte.
             */
            $table->char('session_hash', 64)->nullable()->index();
            $table->timestamp('session_expires_at')->nullable();

            $table->string('consumed_ip', 45)->nullable();
            $table->string('consumed_user_agent', 255)->nullable();

            $table->enum('status', [
                'pending',
                'consumed',
                'completed',
                'expired',
                'canceled',
                'superseded',
            ])->default('pending');

            // Quem liberou. Sem foreign key para `users` — ver
            // `signature_templates`.
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            // A varredura do `signature:expire`.
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
