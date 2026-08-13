<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setor Diretoria.
 *
 * É o terceiro nível da aprovação de ordem de compra: quem decide não é o setor
 * inteiro, e sim os membros ligados ao centro de custo da ordem (ver
 * `purchase_order_cost_center_approvers`). O presidente entra como
 * `coordinator`; os demais diretores, como colaboradores.
 *
 * Vincular as pessoas continua sendo trabalho humano, na tela de Setores —
 * mesmo tratamento do setor Contabilidade. Enquanto ninguém estiver vinculado,
 * nenhuma ordem chega ao nível 3, e a tela de relação avisa disso.
 */
return new class extends Migration
{
    private const NAME = 'Diretoria';

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
            'description' => 'Último nível da aprovação de ordem de compra. Cada diretor responde pelos centros de custo a que está vinculado; o presidente é o coordenador.',
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

        // Setor com gente vinculada não é desfeito por rollback: apagá-lo
        // levaria os vínculos junto (cascade) sem ninguém perceber.
        if (!$sector || DB::table('user_sector')->where('sector_id', $sector->id)->exists()) {
            return;
        }

        DB::table('sectors')->where('id', $sector->id)->delete();
    }
};
