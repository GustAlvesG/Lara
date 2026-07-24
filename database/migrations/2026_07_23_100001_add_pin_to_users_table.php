<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN de 6 dígitos por usuário. Guardado com hash (mesmo tratamento da
     * senha) e usado no Kiosk de assinatura: destrava a sessão de atendimento
     * no tablet e reconfirma cada assinatura do freelancer.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
