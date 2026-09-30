<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setor com **acesso total**: todos os membros alcançam todas as permissões
 * do catálogo (App\Authorization\Permissions). Nasce marcado em Gerência,
 * Diretoria e TI — ver a migration `sync_access_catalog`.
 *
 * É coluna, e não nome de setor escrito no código, para que dar ou tirar o
 * acesso total seja uma decisão da tela de Setores. Não vale para as regras
 * de cargo (aprovar lote, validar contrato, níveis da ordem de compra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->boolean('full_access')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('full_access');
        });
    }
};
