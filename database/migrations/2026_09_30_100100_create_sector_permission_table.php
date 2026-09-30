<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões concedidas a um setor.
 *
 * Tabela própria, e não a `model_has_permissions` polimórfica do Spatie,
 * por causa de `coordinators_only`: "Bot WhatsApp só para o coordenador do
 * Atendimento" precisa de um lugar para dizer *quem* do setor recebe, e a
 * tabela do Spatie não tem essa coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('coordinators_only')->default(false);
            $table->timestamps();
            $table->unique(['sector_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_permission');
    }
};
