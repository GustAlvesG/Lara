<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil do sócio no site (12/11/2026): `usuario` (todo mundo) ou
 * `administrador` (abre os dashboards do site). Definido em Reservas › Contas
 * do site, com a permissão `reservas.contas-site.perfil`.
 *
 * Toda conta existente nasce `usuario`. Idempotente (hasColumn) e com nome de
 * índice curto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'site_role')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->string('site_role', 20)->default('usuario');
            $table->index('site_role', 'members_site_role_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('members', 'site_role')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('members_site_role_idx');
            $table->dropColumn('site_role');
        });
    }
};
