<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caminho da imagem da assinatura desenhada na tela do tablet (Kiosk).
     * Fica no disco público; guarda o traço do freelancer para valor probatório.
     * Nullable: assinaturas antigas (pelo bot) não têm imagem.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('freelancer_signature_path')->nullable()->after('freelancer_signed_by');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn('freelancer_signature_path');
        });
    }
};
