<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Passamos a aceitar o cadastro de freelancers com dados faltantes (o input
     * em massa por planilha, principalmente). Só `name` e `cpf` seguem
     * obrigatórios — o CPF é a chave de identificação. Os demais campos ficam
     * pendentes até serem completados, e é a completude deles que libera a
     * geração do contrato (ver Freelancer::hasCompleteContractData).
     *
     * Alterado via SQL cru porque doctrine/dbal não está instalado no projeto.
     */
    public function up(): void
    {
        foreach (['rg', 'nacionality', 'civil_status', 'address', 'telephone'] as $column) {
            DB::statement("ALTER TABLE freelancers MODIFY {$column} VARCHAR(255) NULL");

            // Cadastros feitos antes da mudança guardam string vazia no lugar do nulo.
            DB::table('freelancers')->where($column, '')->update([$column => null]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['rg', 'nacionality', 'civil_status', 'address', 'telephone'] as $column) {
            DB::table('freelancers')->whereNull($column)->update([$column => '']);

            DB::statement("ALTER TABLE freelancers MODIFY {$column} VARCHAR(255) NOT NULL");
        }
    }
};
