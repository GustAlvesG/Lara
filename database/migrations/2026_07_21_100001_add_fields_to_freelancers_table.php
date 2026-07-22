<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freelancers', function (Blueprint $table) {
            // Chave PIX: quando não informada, assume o valor do CPF (regra no model).
            $table->string('pix_key')->nullable()->after('cpf');
            $table->foreignId('created_by')->nullable()->after('telephone')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // Preenche a chave PIX dos registros já existentes com o CPF.
        DB::table('freelancers')->whereNull('pix_key')->update(['pix_key' => DB::raw('cpf')]);

        Schema::table('freelancers', function (Blueprint $table) {
            $table->unique('cpf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancers', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('pix_key');
        });
    }
};
