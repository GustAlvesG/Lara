<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conferência de identidade, gravada na própria sessão do tablet.
 *
 * Duas colunas, cada uma resolvendo um problema:
 *
 * `identity_confirmed_at` é o que separa "abriu o documento" de "disse quem
 * é". A assinatura exige que ela esteja preenchida — sem isso, um POST direto
 * na rota de assinar pularia a etapa inteira.
 *
 * `identity_attempts` é o teto de tentativas. No modo parcial a conferência é
 * de quatro dígitos, e quatro dígitos não resistem a chutes ilimitados — o
 * mesmo raciocínio do código de liberação do limite semanal dos freelancers,
 * que também é curto e também tem teto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->timestamp('identity_confirmed_at')->nullable()->after('session_expires_at');
            $table->unsignedTinyInteger('identity_attempts')->default(0)->after('identity_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropColumn(['identity_confirmed_at', 'identity_attempts']);
        });
    }
};
