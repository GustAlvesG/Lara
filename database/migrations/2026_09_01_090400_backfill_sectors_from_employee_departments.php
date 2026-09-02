<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um setor para cada departamento já importado.
 *
 * Até aqui o acesso do coordenador se apoiava em
 * `whereIn('department', $nomesDosSetoresQueEleCoordena)` — comparação de
 * texto entre a "Estrutura" do espelho de ponto e o nome do setor. Bastava um
 * acento ou um espaço a mais para o coordenador deixar de enxergar a própria
 * equipe, sem erro nenhum na tela.
 *
 * Esta migration fecha o buraco de uma vez: para cada `department` distinto
 * cria (ou reaproveita) um setor e grava o `sector_id` no funcionário. A
 * comparação por texto acontece **uma vez, aqui**, e nunca mais em tempo de
 * requisição.
 *
 * Setor já existente é reaproveitado sem diferenciar maiúsculas/minúsculas,
 * do mesmo jeito que User::belongsToSectorNamed() compara — assim os setores
 * que já existiam (Gerência, Contabilidade, Comercial, Esporte, RH) não
 * ganham um clone quando aparecem como estrutura no espelho de ponto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'sector_id')) {
            return;
        }

        // Índice dos setores existentes, por nome normalizado.
        $existing = [];
        foreach (DB::table('sectors')->get(['id', 'name']) as $sector) {
            $existing[$this->normalize($sector->name)] = $sector->id;
        }

        $departments = DB::table('employees')
            ->select('department')
            ->distinct()
            ->pluck('department');

        foreach ($departments as $department) {
            $name = trim((string) $department);
            if ($name === '') {
                continue;
            }

            $key = $this->normalize($name);

            if (!isset($existing[$key])) {
                $existing[$key] = DB::table('sectors')->insertGetId([
                    'name' => $name,
                    'description' => 'Departamento importado do espelho de ponto (Banco de Horas).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employees')
                ->whereRaw('LOWER(TRIM(department)) = ?', [mb_strtolower($name)])
                ->whereNull('sector_id')
                ->update(['sector_id' => $existing[$key]]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'sector_id')) {
            return;
        }

        // Só desfaz o vínculo. Os setores criados aqui ficam: a essa altura
        // pode haver gente vinculada a eles, e apagá-los levaria os vínculos
        // junto (cascade em user_sector) sem quem perceba.
        DB::table('employees')->update(['sector_id' => null]);
    }

    /** Mesma normalização usada por User::belongsToSectorNamed(): minúsculas e sem borda. */
    private function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }
};
