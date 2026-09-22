<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O pedido da via pelo próprio signatário, e o registro do envio.
 *
 * `wants_copy` é marcado NO TABLET, na tela de aceite, e chega junto com a
 * assinatura — na mesma requisição e na mesma transação. Foi assim, e não numa
 * pergunta depois da assinatura, porque a sessão do tablet morre no instante
 * em que a assinatura entra: perguntar depois exigiria manter viva uma sessão
 * que já não tem o que fazer.
 *
 * `copy_sent_at` existe para o reenvio pelo painel não virar dúvida ("já foi?")
 * e para o job não mandar duas vezes o mesmo arquivo quando a fila reentrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_signers', function (Blueprint $table) {
            $table->boolean('wants_copy')->default(false)->after('phone');
            $table->timestamp('copy_sent_at')->nullable()->after('wants_copy');
        });
    }

    public function down(): void
    {
        Schema::table('signature_signers', function (Blueprint $table) {
            $table->dropColumn(['wants_copy', 'copy_sent_at']);
        });
    }
};
