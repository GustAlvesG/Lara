<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Support\Placar\PlacarAbilities;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Autenticação da API do Placar Clube: Sanctum, ability única
 * `placar:operar`. Sem token → 401 (não autenticado); com token mas sem a
 * ability → 403 (autenticado, mas sem permissão para operar).
 */
class AutenticacaoTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
    }

    public function test_sem_token_recebe_401(): void
    {
        $this->getJson('/api/placar/modalidades')->assertUnauthorized();
    }

    public function test_token_sem_a_ability_placar_operar_recebe_403(): void
    {
        $cliente = ApiCliente::create(['nome' => 'cliente-sem-ability', 'ativo' => true]);

        Sanctum::actingAs($cliente, ['outra-ability-qualquer']);

        $this->getJson('/api/placar/modalidades')->assertForbidden();
    }

    public function test_token_com_a_ability_placar_operar_acessa_normalmente(): void
    {
        $cliente = ApiCliente::create(['nome' => 'cliente-com-ability', 'ativo' => true]);

        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);

        $this->getJson('/api/placar/ping')->assertOk()->assertJson(['ok' => true]);
    }
}
