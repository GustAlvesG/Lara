<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Congela no contrato a chave PIX conferida pelo freelancer no ato da
     * assinatura.
     *
     * O cadastro do freelancer guarda a chave que vale HOJE; o contrato
     * assinado precisa guardar a que estava à vista quando ele assinou — é ela
     * que o documento cita como destino do pagamento. Sem esta cópia, mudar a
     * chave no cadastro reescreveria, retroativamente, o texto de todo contrato
     * já assinado.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('pix_key')->nullable()->after('price');
            // Preenchido só quando a conferência aconteceu de fato (tablet).
            // Contrato assinado pela API não passa pela tela de conferência e
            // fica com a cópia da chave, mas sem o carimbo.
            $table->dateTime('pix_key_confirmed_at')->nullable()->after('pix_key');
        });
    }

    /**
     * Contratos anteriores ficam com `pix_key` nula: o documento volta a citar
     * a chave do cadastro, que é o que ele fazia antes desta coluna existir.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['pix_key', 'pix_key_confirmed_at']);
        });
    }
};
