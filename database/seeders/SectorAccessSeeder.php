<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ambiente de desenvolvimento: coloca o usuário 1 como coordenador da TI
 * (acesso total), para quem sobe o banco do zero conseguir entrar nas telas
 * de Usuários e Setores.
 *
 * O catálogo de permissões, os setores e a matriz inicial NÃO nascem aqui —
 * são da migration `sync_access_catalog`, que roda em todo ambiente.
 */
class SectorAccessSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::find(1);
        $ti = Sector::whereRaw('LOWER(name) = ?', ['ti'])->first();

        if (! $user || ! $ti) {
            $this->command?->warn('Usuário 1 ou setor TI não encontrado — nada a fazer.');

            return;
        }

        $ti->users()->syncWithoutDetaching([$user->id => ['role' => Sector::ROLE_COORDINATOR]]);

        $this->command?->info("{$user->name} agora coordena a TI (acesso total).");
    }
}
