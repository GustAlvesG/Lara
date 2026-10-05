<?php

namespace Tests\Feature\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\Layout;
use App\Models\Replay\Setting;
use App\Services\Replay\ReplayResolver;
use App\Support\Replay\Orientation;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * A cascata de herança do Replay: QUADRA > ESPORTE > PADRÃO.
 *
 * É a regra que a tela mostra e a câmera obedece, e as duas leem do mesmo
 * resolvedor. Se ela divergir, o Marketing configura uma coisa e a quadra
 * grava outra — sem ninguém perceber até o vídeo sair errado.
 */
class ReplayResolverTest extends TestCase
{
    use CreatesReplaySchema;

    private PlaceGroup $group;
    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReplaySchema();

        $this->group = PlaceGroup::create(['name' => 'Tênis']);
        $this->place = Place::create(['name' => 'Quadra 1', 'place_group_id' => $this->group->id]);
    }

    public function test_sem_configuracao_nenhuma_vale_o_padrao_do_sistema(): void
    {
        $resolved = (new ReplayResolver())->settingFor($this->place);

        $this->assertSame(Orientation::DEFAULT_ORIENTATION, $resolved['orientation']);
        $this->assertSame(Orientation::DEFAULT_CLIP_SECONDS, $resolved['clip_seconds']);
        $this->assertSame('default', $resolved['source']);
    }

    public function test_a_quadra_herda_a_configuracao_do_esporte(): void
    {
        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 15,
        ]);

        $resolved = (new ReplayResolver())->settingFor($this->place);

        $this->assertSame(Orientation::VERTICAL, $resolved['orientation']);
        $this->assertSame(15, $resolved['clip_seconds']);
        $this->assertSame('group', $resolved['source']);
    }

    public function test_a_configuracao_da_quadra_vence_a_do_esporte(): void
    {
        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 15,
        ]);

        Setting::create([
            'place_id' => $this->place->id,
            'orientation' => Orientation::HORIZONTAL,
            'clip_seconds' => 45,
        ]);

        $resolved = (new ReplayResolver())->settingFor($this->place);

        $this->assertSame(Orientation::HORIZONTAL, $resolved['orientation']);
        $this->assertSame(45, $resolved['clip_seconds']);
        $this->assertSame('place', $resolved['source']);
    }

    public function test_configuracao_inativa_da_quadra_nao_atrapalha_a_heranca(): void
    {
        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 20,
        ]);

        Setting::create([
            'place_id' => $this->place->id,
            'orientation' => Orientation::HORIZONTAL,
            'clip_seconds' => 45,
            'active' => false,
        ]);

        $resolved = (new ReplayResolver())->settingFor($this->place);

        // Desativar a exceção devolve a quadra ao esporte — e não ao padrão
        // do sistema, que é o erro fácil de cometer aqui.
        $this->assertSame(Orientation::VERTICAL, $resolved['orientation']);
        $this->assertSame('group', $resolved['source']);
    }

    public function test_duracao_fora_da_faixa_gravada_no_banco_e_contida(): void
    {
        Setting::create([
            'place_id' => $this->place->id,
            'orientation' => Orientation::HORIZONTAL,
            'clip_seconds' => 600,
        ]);

        $this->assertSame(
            Orientation::MAX_CLIP_SECONDS,
            (new ReplayResolver())->settingFor($this->place)['clip_seconds'],
        );
    }

    public function test_o_layout_segue_a_orientacao_resolvida(): void
    {
        Setting::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 30,
        ]);

        $vertical = Layout::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::VERTICAL,
            'name' => 'Patrocínio vertical',
        ]);

        Layout::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::HORIZONTAL,
            'name' => 'Patrocínio horizontal',
        ]);

        $resolved = (new ReplayResolver())->resolveFor($this->place);

        $this->assertSame($vertical->id, $resolved['layout']->id);
        $this->assertSame('group', $resolved['layout_source']);
    }

    public function test_layout_da_quadra_vence_o_do_esporte(): void
    {
        Layout::create([
            'place_group_id' => $this->group->id,
            'orientation' => Orientation::HORIZONTAL,
            'name' => 'Do esporte',
        ]);

        $proprio = Layout::create([
            'place_id' => $this->place->id,
            'orientation' => Orientation::HORIZONTAL,
            'name' => 'Só desta quadra',
        ]);

        $resolved = (new ReplayResolver())->resolveFor($this->place);

        $this->assertSame($proprio->id, $resolved['layout']->id);
        $this->assertSame('place', $resolved['layout_source']);
    }

    public function test_config_hash_muda_quando_a_configuracao_muda(): void
    {
        $resolver = new ReplayResolver();
        $antes = $resolver->configHash();

        Setting::create([
            'place_id' => $this->place->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 30,
        ]);

        // Instância nova: o hash é do estado publicado, não do que este
        // objeto carregou em memória.
        $this->assertNotSame($antes, (new ReplayResolver())->configHash());
    }
}
