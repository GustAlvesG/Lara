<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conferência de identidade por CÓDIGO ENVIADO POR E-MAIL.
     *
     * O modelo ganha a opção `email`: no tablet, o servidor manda um código de
     * 6 números ao e-mail do signatário, e a pessoa o digita. O código fica na
     * LIBERAÇÃO (é dela a sessão do tablet), só como HMAC — quem lê o banco não
     * o descobre —, com prazo e contagem de envios.
     */
    public function up(): void
    {
        Schema::table('signature_templates', function (Blueprint $table) {
            $table->enum('identity_check', ['partial', 'full', 'none', 'email'])->default('partial')->change();
        });

        Schema::table('signature_requests', function (Blueprint $table) {
            $table->char('identity_code_hash', 64)->nullable()->after('identity_attempts');
            $table->timestamp('identity_code_expires_at')->nullable()->after('identity_code_hash');
            $table->timestamp('identity_code_sent_at')->nullable()->after('identity_code_expires_at');
            $table->unsignedTinyInteger('identity_code_sends')->default(0)->after('identity_code_sent_at');
        });
    }

    public function down(): void
    {
        // Volta só se nenhum modelo usa a opção: reescrever a regra de um
        // modelo para caber no enum antigo mudaria o que ele exige.
        if (DB::table('signature_templates')->where('identity_check', 'email')->exists()) {
            throw new RuntimeException('Há modelos com conferência por código no e-mail: a coluna não pode voltar ao enum antigo.');
        }

        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropColumn(['identity_code_hash', 'identity_code_expires_at', 'identity_code_sent_at', 'identity_code_sends']);
        });

        Schema::table('signature_templates', function (Blueprint $table) {
            $table->enum('identity_check', ['partial', 'full', 'none'])->default('partial')->change();
        });
    }
};
