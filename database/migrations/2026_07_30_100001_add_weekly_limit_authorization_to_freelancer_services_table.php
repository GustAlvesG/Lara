<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contrato registrado acima do limite de 7 dias só existe porque o
     * coordenador do Comercial liberou com o próprio PIN. Sem gravar quem
     * liberou e quando, a autorização não deixa rastro e a regra vira apenas um
     * aviso na tela.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->timestamp('weekly_limit_authorized_at')->nullable()->after('updated_by');
            $table->foreignId('weekly_limit_authorized_by')->nullable()->after('weekly_limit_authorized_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_limit_authorized_by');
            $table->dropColumn('weekly_limit_authorized_at');
        });
    }
};
