<?php

namespace Tests\Feature\Replay;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\Setting;
use App\Models\Replay\Video;
use App\Services\Replay\MediaService;
use App\Support\Replay\Orientation;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * O seed de clipes de demonstração.
 *
 * Testa as duas coisas que fazem dele seguro: ele respeita a orientação
 * configurada de cada quadra (senão o site seria desenvolvido contra um
 * formato que não existe na vida real) e o `--only-clear` alcança **apenas** o
 * que ele mesmo criou.
 *
 * Depende de ffmpeg de verdade — é ele quem gera o arquivo. Sem ffmpeg na
 * máquina, o teste é pulado em vez de falhar: o comando é ferramenta de
 * desenvolvimento, e o servidor pode legitimamente não ter o binário.
 */
class ReplayDemoVideosCommandTest extends TestCase
{
    use CreatesReplaySchema;

    private Place $horizontal;
    private Place $vertical;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->ffmpegDisponivel()) {
            $this->markTestSkipped('ffmpeg não encontrado nesta máquina.');
        }

        $this->createReplaySchema();
        Storage::fake('replay');

        $group = PlaceGroup::create(['name' => 'Tênis']);
        $this->horizontal = Place::create(['name' => 'Quadra 1', 'place_group_id' => $group->id]);
        $this->vertical = Place::create(['name' => 'Quadra 2', 'place_group_id' => $group->id]);

        // Só a segunda quadra é vertical: o pacote tem de sair com as duas
        // orientações quando o cadastro tem as duas.
        Setting::create([
            'place_id' => $this->vertical->id,
            'orientation' => Orientation::VERTICAL,
            'clip_seconds' => 30,
        ]);
    }

    private function ffmpegDisponivel(): bool
    {
        try {
            return Process::timeout(15)
                ->run([(string) config('services.replay.ffmpeg', 'ffmpeg'), '-version'])
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function gerar(array $options = []): void
    {
        $this->artisan('replay:demo-videos', array_merge([
            '--per-place' => 2,
            '--seconds' => 1,
            '--force' => true,
        ], $options))->assertSuccessful();
    }

    public function test_gera_dois_clipes_por_quadra_com_arquivo_em_disco(): void
    {
        $this->gerar();

        $this->assertSame(4, Video::count());

        foreach (Video::all() as $video) {
            Storage::disk('replay')->assertExists($video->file_path);
            $this->assertGreaterThan(0, $video->size_bytes);
            $this->assertStringStartsWith('demo-', $video->external_id);
        }
    }

    public function test_respeita_a_orientacao_configurada_de_cada_quadra(): void
    {
        $this->gerar();

        $this->assertSame(
            [Orientation::HORIZONTAL],
            Video::where('place_id', $this->horizontal->id)->pluck('orientation')->unique()->values()->all(),
        );

        $this->assertSame(
            [Orientation::VERTICAL],
            Video::where('place_id', $this->vertical->id)->pluck('orientation')->unique()->values()->all(),
        );
    }

    public function test_as_gravacoes_ficam_no_passado_e_em_dias_diferentes(): void
    {
        $this->gerar();

        $datas = Video::where('place_id', $this->horizontal->id)->pluck('recorded_at');

        foreach ($datas as $data) {
            $this->assertTrue($data->isPast(), 'Vídeo de demonstração não pode ter data futura.');
        }

        // Dias distintos: é o que dá ao filtro de data da galeria o que filtrar.
        $this->assertCount(2, $datas->map(fn ($d) => $d->toDateString())->unique());
    }

    public function test_only_clear_remove_os_demos_e_poupa_os_videos_de_verdade(): void
    {
        $this->gerar();

        $real = Video::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'place_id' => $this->horizontal->id,
            'place_group_id' => $this->horizontal->place_group_id,
            'external_id' => 'clip-da-camera-001',
            'recorded_at' => now()->subHour(),
            'duration_seconds' => 30,
            'orientation' => Orientation::HORIZONTAL,
            'file_path' => MediaService::VIDEO_DIR . '/2026/09/real.mp4',
            'size_bytes' => 1024,
            'expires_at' => now()->addDays(6),
        ]);
        Storage::disk('replay')->put($real->file_path, 'conteudo');

        $this->artisan('replay:demo-videos', ['--only-clear' => true, '--force' => true])->assertSuccessful();

        $this->assertSame(1, Video::count());
        $this->assertSame($real->id, Video::first()->id);
        Storage::disk('replay')->assertExists($real->file_path);
    }

    public function test_pode_limitar_a_uma_quadra(): void
    {
        $this->gerar(['--place' => [$this->vertical->id]]);

        $this->assertSame(2, Video::count());
        $this->assertSame(0, Video::where('place_id', $this->horizontal->id)->count());
    }
}
