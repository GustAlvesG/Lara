<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O catálogo de funções passa a atender duas modalidades com contas
 * diferentes:
 *
 *  - `freelancer` — preço por bloco de 15 minutos (o que sempre existiu);
 *  - `cache`      — valor fixo por faixa de horas, sem proporção (ver a
 *                   migration das faixas).
 *
 * O tipo é exclusivo: "Garçom Freelancer" e "Garçom Cachê" são dois
 * cadastros, porque as contas e os fluxos de aprovação não se misturam.
 * Todo cadastro existente é freelancer — daí o default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('function_freelancers', function (Blueprint $table) {
            $table->string('type', 20)->default('freelancer')->after('description')->index();
            // Função de cachê não tem preço por bloco: quem responde pelo valor
            // é a tabela de faixas.
            $table->decimal('price', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('function_freelancers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });

        // O price só volta a NOT NULL se não houver função de cachê gravada;
        // como o down() já apagou o tipo, deixá-lo nullable é o seguro.
    }
};
