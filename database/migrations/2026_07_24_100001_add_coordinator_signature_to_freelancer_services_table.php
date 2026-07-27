<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caminho da imagem da assinatura do coordenador desenhada no tablet (Kiosk).
     * Espelha freelancer_signature_path. Nullable: assinaturas dadas pelo painel
     * continuam sendo só eletrônicas (data + usuário), sem traço.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('coordinator_signature_path')->nullable()->after('coordinator_signed_by');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('coordinator_signature_path');
        });
    }
};
