<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nem todo freelancer tem e-mail; o contato obrigatório é o telefone.
     *
     * Alterado via SQL cru porque doctrine/dbal não está instalado no projeto.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE freelancers MODIFY email VARCHAR(255) NULL');

        // Cadastros feitos antes da mudança guardam string vazia no lugar do nulo.
        DB::table('freelancers')->where('email', '')->update(['email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('freelancers')->whereNull('email')->update(['email' => '']);

        DB::statement('ALTER TABLE freelancers MODIFY email VARCHAR(255) NOT NULL');
    }
};
