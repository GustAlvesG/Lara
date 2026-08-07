<?php

namespace Tests\Feature\Placar\Web;

use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * As telas web do Placar Clube (cadastro e scout) são restritas a quem
 * está no setor Esporte — ver User::canAccessPlacar() e os Gates
 * `manage-placar-cadastro`/`view-placar-scout` no AppServiceProvider.
 */
class AutorizacaoTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
    }

    public function test_quem_nao_esta_no_setor_esporte_recebe_403_no_cadastro(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte(false))
            ->get(route('placar.equipes.index'))
            ->assertForbidden();
    }

    public function test_quem_nao_esta_no_setor_esporte_recebe_403_no_scout(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte(false))
            ->get(route('placar.scout.artilharia'))
            ->assertForbidden();
    }

    public function test_quem_esta_no_setor_esporte_acessa_o_cadastro(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte(true))
            ->get(route('placar.equipes.index'))
            ->assertOk();
    }

    public function test_quem_esta_no_setor_esporte_acessa_o_scout(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte(true))
            ->get(route('placar.scout.artilharia'))
            ->assertOk();
    }

    public function test_visitante_sem_sessao_e_redirecionado_ao_login(): void
    {
        $this->get(route('placar.equipes.index'))->assertRedirect(route('login'));
    }
}
