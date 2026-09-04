<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas mudanças no cadastro de funcionário:
 *
 * 1. `sector_id` — o vínculo com o setor deixa de ser casamento de texto
 *    (`employees.department` × `sectors.name`) e passa a ser chave estrangeira.
 *    A coluna `department` continua existindo de propósito: é o texto cru que
 *    veio da "Estrutura" do espelho de ponto, e serve de auditoria quando o
 *    RH renomeia a estrutura no sistema de ponto.
 *
 * 2. Rescisão — data e motivo. São **informativos**: não escondem o
 *    funcionário nem congelam saldo. Existem para o cadastro do RH e para o
 *    módulo de consulta de funcionários ativos que vem depois (ver o escopo
 *    `Employee::active()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->after('department')
                    ->constrained('sectors')->nullOnDelete();
            }

            if (!Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('sector_id');
            }

            if (!Schema::hasColumn('employees', 'termination_reason')) {
                $table->string('termination_reason')->nullable()->after('termination_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'sector_id')) {
                $table->dropForeign(['sector_id']);
                $table->dropColumn('sector_id');
            }

            foreach (['termination_date', 'termination_reason'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
