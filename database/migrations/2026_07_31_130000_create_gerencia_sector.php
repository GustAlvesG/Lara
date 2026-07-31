<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setor Gerência.
 *
 * A aprovação do lote antes da diretoria deixou de ser da role `admin` e passou
 * a ser do **coordenador deste setor** (ver User::isManagementCoordinator). O
 * setor é criado aqui para que a regra tenha onde se apoiar já no deploy —
 * vincular quem é o coordenador continua sendo trabalho humano, na tela de
 * Setores. Enquanto ninguém for vinculado, a fila de aprovação fica sem dono.
 */
return new class extends Migration
{
    private const NAME = 'Gerência';

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
            'description' => 'Aprova os lotes de contratos de freelancer antes do envio à diretoria.',
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

        DB::table('sectors')->where('id', $sector->id)->delete();
    }
};
