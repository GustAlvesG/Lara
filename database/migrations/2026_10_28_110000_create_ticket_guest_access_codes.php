<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Meus ingressos" do não sócio no site (28/10/2026): a pessoa informa CPF e
 * e-mail da compra e recebe um código de 6 dígitos no e-mail. Aqui fica só o
 * hash do código (HMAC com a APP_KEY), as tentativas e o prazo. O acesso
 * depois do código é um token cifrado, sem tabela (TicketGuestAccessService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_guest_access_codes')) {
            return;
        }

        Schema::create('ticket_guest_access_codes', function (Blueprint $table) {
            $table->id();
            $table->string('cpf', 11);
            // Minúsculo, como a comparação com o e-mail do pedido.
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['cpf', 'email', 'created_at'], 'tgac_cpf_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_guest_access_codes');
    }
};
