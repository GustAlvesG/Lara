<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos', function (Blueprint $table) {
            $table->boolean('mandatory')->default(false)->after('privacy');
        });

        // Ciência de leitura: um registro por usuário/aviso. Diferente de
        // aviso_views (que acumula um registro por acesso), aqui o que importa
        // é o "assinou uma vez", por isso a chave única.
        Schema::create('aviso_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();

            $table->unique(['aviso_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_acknowledgements');

        Schema::table('avisos', function (Blueprint $table) {
            $table->dropColumn('mandatory');
        });
    }
};
