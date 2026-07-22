<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Quem assina é o freelancer, mas a assinatura acontece pelo bot, com um
     * usuário do sistema conduzindo o atendimento. Guardamos qual login
     * auxiliou (e reconfirmou a senha) nessa assinatura.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->foreignId('freelancer_signed_by')->nullable()->after('freelancer_signed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('freelancer_signed_by');
        });
    }
};
