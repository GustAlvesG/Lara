<?php

namespace Tests\Feature\Authorization;

use App\Authorization\AccessManager;
use App\Authorization\Permissions as P;
use App\Models\Sector;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Setores › Por módulo: a matriz setor → permissão virada por módulo, com os
 * setores de acesso total e as permissões individuais dadas e negadas.
 */
class SectorsByModuleTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            // O layout consulta notificações e avisos em toda tela.
            'database/migrations/2026_06_10_000001_create_notifications_table.php',
            'database/migrations/2026_06_10_000002_create_avisos_table.php',
            'database/migrations/2026_06_10_000003_avisos_privacy_and_lembretes.php',
            'database/migrations/2026_06_10_000004_create_aviso_views_table.php',
            'database/migrations/2026_06_10_000006_create_tags_tables.php',
            'database/migrations/2026_06_12_000002_create_aviso_user_table.php',
            'database/migrations/2026_07_31_120000_add_mandatory_reading_to_avisos.php',
        ]]);
        $this->shareSqliteWithUserConnection();
    }

    public function test_lista_os_setores_de_cada_modulo_com_o_que_alcancam(): void
    {
        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');

        $manager = app(AccessManager::class);
        $esporte = Sector::findOrFail($this->sectorId('Esporte'));
        $manager->syncSectorPermissions($admin, $esporte, [
            P::INGRESSOS_GERENCIAR => false,
            P::INGRESSOS_CHECKIN => true,
        ]);

        $pessoa = $this->makeUser('Fulana Negada');
        $manager->syncUserPermissions($admin, $pessoa, [P::INGRESSOS_CUPONS], [P::INGRESSOS_VENDAS]);

        $html = $this->actingAs($admin)
            ->get(route('sectors.modules'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ingressos', $html);
        $this->assertStringContainsString(e(P::label(P::INGRESSOS_GERENCIAR)), $html);
        $this->assertStringContainsString('só coordenadores', $html);
        $this->assertStringContainsString(route('sectors.show', $esporte->id), $html);
        $this->assertStringContainsString('Fulana Negada', $html);
        $this->assertStringContainsString(e(P::label(P::INGRESSOS_VENDAS)), $html);
        $this->assertStringContainsString('Acesso total', $html);
    }

    public function test_sem_setores_gerenciar_nao_abre(): void
    {
        $user = $this->makeUser('Sem Gestão');

        $this->actingAs($user)->get(route('sectors.modules'))->assertForbidden();
    }
}
