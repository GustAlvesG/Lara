<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara `employees` para o cadastro do módulo Funcionários (CRUD do RH e
 * importação por planilha), sem apagar nada:
 *
 * 1. **CPF só com dígitos.** O espelho de ponto gravava com e sem máscara, e
 *    toda comparação que esquecia de tirar a pontuação errava em silêncio
 *    (a Frota errava 2/3 das buscas por CPF). Daqui em diante o model grava
 *    sempre só os dígitos; esta migration acerta o que já existe.
 *
 * 2. **Cargo, estrutura e admissão deixam de ser obrigatórios.** Eram
 *    obrigatórios porque só a importação criava funcionário, e o espelho
 *    sempre os traz. Quem nasce pelo cadastro manual ou pela planilha do RH
 *    pode não ter a "Estrutura" do ponto.
 *
 * 3. **Origem do cadastro** (`source`): import (espelho de ponto), manual
 *    (tela) ou planilha (upload do RH). Só para auditoria.
 *
 * 4. **Setor obrigatório.** Todo funcionário tem setor. A coluna só vira NOT
 *    NULL — e a chave passa de `nullOnDelete` para `restrictOnDelete`, para
 *    excluir um setor nunca deixar funcionário órfão — se nenhum funcionário
 *    estiver sem setor. Havendo algum, a coluna fica como está, o deploy
 *    segue, e o aviso vai para o log. As telas e a planilha já exigem o
 *    setor; corrigidos os que faltam, a trava de banco entra numa migration
 *    nova (ver docs/funcionalidades/funcionarios.md). No SQLite dos testes
 *    essa troca de chave não é feita.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->where(function ($q) {
                $q->where('cpf', 'like', '%.%')
                    ->orWhere('cpf', 'like', '%-%')
                    ->orWhere('cpf', 'like', '% %');
            })
            ->update(['cpf' => DB::raw("REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '')")]);

        Schema::table('employees', function (Blueprint $table) {
            $table->string('position')->nullable()->change();
            $table->string('department')->nullable()->change();
            $table->date('admission_date')->nullable()->change();

            if (!Schema::hasColumn('employees', 'source')) {
                $table->string('source', 20)->default('import')->after('termination_reason');
            }
        });

        $this->requireSector();
    }

    public function down(): void
    {
        // A máscara do CPF e a obrigatoriedade antiga não voltam: recolocar
        // máscara não traz nada de volta e NOT NULL em position/department
        // quebraria quem nasceu pelo cadastro manual.
        if (Schema::hasColumn('employees', 'source')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }

    private function requireSector(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $withoutSector = DB::table('employees')->whereNull('sector_id')->count();

        if ($withoutSector > 0) {
            Log::warning("prepare_employees_for_registry: {$withoutSector} funcionário(s) sem setor — employees.sector_id continua aceitando nulo até serem corrigidos.");

            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable(false)->change();
            $table->foreign('sector_id')->references('id')->on('sectors')->restrictOnDelete();
        });
    }
};
