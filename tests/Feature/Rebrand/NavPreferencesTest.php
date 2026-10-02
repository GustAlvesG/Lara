<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\UserAccess;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Favoritos e ordem do menu ficam na conta, e não no navegador: limpar os
 * dados do site não pode mais apagar os favoritos de ninguém.
 */
class NavPreferencesTest extends TestCase
{
    use MigratesAccessSchema;
    use RendersScreens;
    use SharesSqliteWithUserConnection;

    private function comBanco(): void
    {
        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_01_100000_add_nav_preferences_to_users_table.php']);
        $this->shareSqliteWithUserConnection();
    }

    public function test_favoritos_e_ordem_sao_gravados_na_conta(): void
    {
        $this->comBanco();
        $pessoa = $this->makeUser('Marina');

        $this->actingAs($pessoa)
            ->putJson('/nav-preferences', ['favorites' => ['parking.search', 'avisos.index', 'parking.search'], 'order' => ['siv', 'infoclube']])
            ->assertOk()
            ->assertJson(['favorites' => ['parking.search', 'avisos.index'], 'order' => ['siv', 'infoclube']]);

        $this->assertSame(
            ['favorites' => ['parking.search', 'avisos.index'], 'order' => ['siv', 'infoclube']],
            $pessoa->fresh()->nav_preferences
        );

        // Tirar o último favorito também é salvar: lista vazia, não "nunca salvou".
        $this->actingAs($pessoa)->putJson('/nav-preferences', ['favorites' => [], 'order' => []])->assertOk();
        $this->assertSame(['favorites' => [], 'order' => []], $pessoa->fresh()->nav_preferences);
    }

    public function test_recusa_lixo_e_quem_nao_esta_logado(): void
    {
        $this->putJson('/nav-preferences', ['favorites' => [], 'order' => []])->assertUnauthorized();

        $this->comBanco();
        $this->actingAs($this->makeUser('Marina'))
            ->putJson('/nav-preferences', ['favorites' => 'parking.search', 'order' => []])
            ->assertUnprocessable();
    }

    public function test_layout_entrega_os_favoritos_da_conta_ao_menu(): void
    {
        $pessoa = $this->usuario(new UserAccess([]));
        $pessoa->nav_preferences = ['favorites' => ['avisos.index'], 'order' => []];

        $html = $this->tela($pessoa, 'avisos.index', [], 'avisos.index', [
            'avisos' => collect(), 'expirados' => collect(), 'todos' => collect(), 'search' => null,
        ]);

        // O que veio da conta entra no shell; a gravação vai por URL fixa.
        $this->assertStringContainsString('avisos.index', $html);
        $this->assertMatchesRegularExpression('#laraShell\(.*nav-preferences#s', $html);
        $this->assertStringContainsString('syncPrefs()', $html);
    }
}
