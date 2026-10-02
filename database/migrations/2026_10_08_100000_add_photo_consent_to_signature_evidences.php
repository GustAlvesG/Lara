<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autorização para a captura da imagem (foto) de quem assina.
     *
     * A pessoa marca no tablet, na tela de aceite, que autoriza a foto e a
     * guarda dela. Fica na evidência o fato (`photo_consent`) e o TEXTO que
     * ela leu (`photo_consent_text`): a redação da autorização pode mudar, e o
     * que vale para cada assinatura é o que estava na tela naquele dia.
     *
     * Nulo nas assinaturas anteriores a esta coluna e nas que não têm foto.
     */
    public function up(): void
    {
        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->boolean('photo_consent')->default(false);
            $table->text('photo_consent_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->dropColumn(['photo_consent', 'photo_consent_text']);
        });
    }
};
