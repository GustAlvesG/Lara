<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\AvisoAcknowledgement;
use App\Models\User;
use Database\Seeders\AvisoNovoDesignSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Leitura obrigatória de avisos, de ponta a ponta: quem recebe o aviso é
 * desviado para a tela cheia de ciência em qualquer navegação, confirma e
 * segue; só coordenador pode exigir leitura; o seeder do novo visual publica
 * um aviso assim.
 */
class MandatoryAvisoReadingTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            // O layout lê as notificações em toda tela.
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

    private function aviso(User $autor, array $attributes = []): Aviso
    {
        return Aviso::create(array_merge([
            'title' => 'Portão 2 fechado para obra',
            'content' => '<p>Use o portão 1 até sexta.</p>',
            'privacy' => Aviso::PRIVACY_PUBLICO,
            'mandatory' => true,
            'created_by' => $autor->id,
        ], $attributes));
    }

    public function test_quem_recebe_o_aviso_e_levado_para_a_tela_cheia_e_segue_depois_de_confirmar(): void
    {
        $autor = $this->makeUser('Coordenadora');
        $pessoa = $this->makeUser('Porteiro');
        $aviso = $this->aviso($autor);

        // Qualquer tela leva à ciência — é o que acontece logo depois do login.
        $this->actingAs($pessoa)->get('/profile')->assertRedirect(route('avisos.pending'));

        $tela = $this->actingAs($pessoa)->get(route('avisos.pending'))->assertOk();
        $tela->assertSee('Portão 2 fechado para obra');
        $tela->assertSee('Use o portão 1 até sexta.');
        $tela->assertSee(route('avisos.acknowledge', $aviso), false);
        // Tela cheia: sem o menu do painel.
        $tela->assertDontSee('lara-launcher', false);

        // Sem marcar que leu, não confirma.
        $this->actingAs($pessoa)->post(route('avisos.acknowledge', $aviso))->assertSessionHasErrors('confirm');
        $this->assertSame(0, AvisoAcknowledgement::count());

        // Confirma e volta para onde ia.
        $this->actingAs($pessoa)->post(route('avisos.acknowledge', $aviso), ['confirm' => '1'])
            ->assertRedirect(url('/profile'));
        $this->assertSame(1, AvisoAcknowledgement::where('user_id', $pessoa->id)->where('aviso_id', $aviso->id)->count());

        // Daí em diante a navegação é livre.
        $this->assertFalse(Aviso::mandatoryPendingFor($pessoa)->exists());
    }

    public function test_so_e_cobrado_de_quem_o_aviso_alcanca(): void
    {
        $autor = $this->makeUser('Coordenadora');
        $this->joinSector($autor, 'Portaria', 'coordinator');
        $daPortaria = $this->makeUser('Porteiro');
        $this->joinSector($daPortaria, 'Portaria');
        $deFora = $this->makeUser('Recepcionista');
        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');

        $this->aviso($autor, ['privacy' => Aviso::PRIVACY_SETOR]);
        $this->aviso($autor, ['title' => 'Comum', 'mandatory' => false]);
        $this->aviso($autor, ['title' => 'Vencido', 'expires_at' => today()->subDay()]);

        $this->assertSame(1, Aviso::mandatoryPendingFor($daPortaria)->count());
        $this->assertSame(0, Aviso::mandatoryPendingFor($deFora)->count());
        // O autor não confirma o próprio aviso; acesso total vê tudo, mas só
        // dá ciência do que foi escrito para ele.
        $this->assertSame(0, Aviso::mandatoryPendingFor($autor)->count());
        $this->assertSame(0, Aviso::mandatoryPendingFor($admin)->count());
        $this->assertSame(3, Aviso::visibleTo($admin)->count());
    }

    public function test_so_coordenador_pode_exigir_leitura(): void
    {
        $coordenadora = $this->makeUser('Coordenadora');
        $this->joinSector($coordenadora, 'Portaria', 'coordinator');
        $colaborador = $this->makeUser('Porteiro');
        $this->joinSector($colaborador, 'Portaria');

        $dados = ['title' => 'Reunião geral', 'content' => 'Sexta, 9h.', 'privacy' => 'pessoa', 'mandatory' => '1'];

        $this->actingAs($colaborador)->post(route('avisos.store'), $dados)->assertRedirect();
        $this->assertFalse(Aviso::where('created_by', $colaborador->id)->firstOrFail()->mandatory);

        $this->actingAs($coordenadora)->post(route('avisos.store'), $dados)->assertRedirect();
        $this->assertTrue(Aviso::where('created_by', $coordenadora->id)->firstOrFail()->mandatory);

        // O campo só aparece para quem pode usar.
        $this->actingAs($coordenadora)->get(route('avisos.create'))->assertSee('name="mandatory"', false);
        $this->actingAs($colaborador)->get(route('avisos.create'))->assertDontSee('name="mandatory"', false);
    }

    public function test_seeder_publica_o_aviso_do_novo_visual_como_leitura_obrigatoria(): void
    {
        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');
        $pessoa = $this->makeUser('Porteiro');

        $this->seed(AvisoNovoDesignSeeder::class);
        $this->seed(AvisoNovoDesignSeeder::class);

        $aviso = Aviso::where('title', AvisoNovoDesignSeeder::TITLE)->get();
        $this->assertCount(1, $aviso, 'Rodar o seeder duas vezes não pode duplicar o aviso.');
        $this->assertTrue($aviso->first()->mandatory);
        $this->assertSame(Aviso::PRIVACY_PUBLICO, $aviso->first()->privacy);
        $this->assertSame($admin->id, $aviso->first()->created_by);

        $this->assertTrue(Aviso::mandatoryPendingFor($pessoa)->exists());
        $this->actingAs($pessoa)->get('/profile')->assertRedirect(route('avisos.pending'));
    }

    public function test_falha_na_consulta_nao_derruba_a_navegacao(): void
    {
        $pessoa = $this->makeUser('Porteiro');
        \Illuminate\Support\Facades\Schema::drop('aviso_acknowledgements');
        \Illuminate\Support\Facades\Schema::drop('aviso_views');
        \Illuminate\Support\Facades\Schema::drop('aviso_user');
        \Illuminate\Support\Facades\Schema::drop('avisos');

        // Sem as tabelas (migration não aplicada), a tela pedida abre normalmente.
        $this->actingAs($pessoa)->get('/profile')->assertOk();
    }
}
