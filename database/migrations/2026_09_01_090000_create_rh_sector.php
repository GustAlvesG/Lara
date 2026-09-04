<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setor RH.
 *
 * O Banco de Horas deixou de ter "RH" e "TI" como nomes soltos dentro do
 * controller (ver CompTimeController::getAccessRestriction, versão antiga) e
 * passou a se apoiar neste setor: quem está no RH, em qualquer papel, importa
 * o espelho de ponto, enxerga todos os funcionários e administra o cadastro
 * (férias, afastamento, rescisão) — ver User::canManageCompTime().
 *
 * O setor é criado aqui para que a regra tenha onde se apoiar já no deploy;
 * vincular as pessoas continua sendo trabalho humano, na tela de Setores.
 * Enquanto ninguém for vinculado, o acesso total só existe para quem tiver a
 * permissão `import comp time` (ver a migration irmã).
 */
return new class extends Migration
{
    private const NAME = 'RH';

    public function up(): void
    {
        if (!Schema::hasTable('sectors')) {
            return;
        }

        $exists = DB::table('sectors')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::NAME)])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('sectors')->insert([
            'name' => self::NAME,
            'description' => 'Responde pelo Banco de Horas: importa o espelho de ponto e administra o cadastro dos funcionários.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('sectors')) {
            return;
        }

        $sector = DB::table('sectors')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::NAME)])
            ->first();

        // Setor com gente vinculada não é desfeito por rollback de migration —
        // apagá-lo levaria junto os vínculos (cascade) sem quem perceba.
        if (!$sector || DB::table('user_sector')->where('sector_id', $sector->id)->exists()) {
            return;
        }

        // Nem setor que já virou o departamento de algum funcionário.
        if (Schema::hasColumn('employees', 'sector_id')
            && DB::table('employees')->where('sector_id', $sector->id)->exists()) {
            return;
        }

        DB::table('sectors')->where('id', $sector->id)->delete();
    }
};
