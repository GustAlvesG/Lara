<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O valor da função é cobrado por bloco de 15 minutos, então total_hours
     * precisa aceitar frações de hora (1.25, 1.5, 1.75...).
     *
     * Alterado via SQL cru porque doctrine/dbal não está instalado no projeto.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE freelancer_services MODIFY total_hours DECIMAL(6,2) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE freelancer_services MODIFY total_hours INT NOT NULL');
    }
};
