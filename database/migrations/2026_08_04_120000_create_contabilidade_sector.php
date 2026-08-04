<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setor Contabilidade.
 *
 * O financeiro dos freelancers deixou de ser a permissão `manage freelancer
 * payments` (que nascia na role `admin`) e passou a ser **vínculo de setor**:
 * quem está na Contabilidade ou na Gerência, em qualquer papel, acessa a aba e
 * dá baixa — ver User::canManageFreelancerPayments().
 *
 * O setor é criado aqui para que a regra tenha onde se apoiar já no deploy;
 * vincular as pessoas continua sendo trabalho humano, na tela de Setores.
 * Enquanto ninguém for vinculado a nenhum dos dois setores, **o Financeiro
 * fica sem dono** e a aba não aparece para ninguém.
 */
return new class extends Migration
{
    private const NAME = 'Contabilidade';

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
            'description' => 'Responde pelo financeiro dos freelancers: confere os contratos aprovados e dá baixa de pagamento.',
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
