<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos (06/11/2026): edição do modelo pela Biblioteca. Guarda quem
 * editou por último, para a lista de modelos dizer ("por Fulana"), como as
 * cláusulas já dizem.
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contract_models') && !Schema::hasColumn('signature_contract_models', 'updated_by_name')) {
            Schema::table('signature_contract_models', function (Blueprint $table) {
                $table->string('updated_by_name', 120)->nullable()->after('created_by_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contract_models', 'updated_by_name')) {
            Schema::table('signature_contract_models', function (Blueprint $table) {
                $table->dropColumn('updated_by_name');
            });
        }
    }
};
