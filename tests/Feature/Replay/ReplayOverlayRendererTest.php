<?php

namespace Tests\Feature\Replay;

use App\Models\PlaceGroup;
use App\Models\Replay\Layout;
use App\Services\Replay\MediaService;
use App\Services\Replay\OverlayRenderer;
use App\Support\Replay\Orientation;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesReplaySchema;
use Tests\TestCase;

/**
 * A composição do overlay.
 *
 * É a peça cujo defeito só apareceria no vídeo, depois do jogo: se a tela de
 * composição sair no tamanho errado, ou se a transparência virar preto, o
 * sistema de captura queima um retângulo opaco por cima da jogada. Por isso
 * os testes olham os pixels, e não só o arquivo existir.
 */
class ReplayOverlayRendererTest extends TestCase
{
    use CreatesReplaySchema;

    private Layout $layout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createReplaySchema();
        Storage::fake('replay');

        $group = PlaceGroup::create(['name' => 'Tênis']);

        $this->layout = Layout::create([
            'place_group_id' => $group->id,
            'orientation' => Orientation::HORIZONTAL,
            'name' => 'Patrocínio',
        ]);
    }

    /** Um PNG vermelho e opaco, para ser fácil de achar no resultado. */
    private function logo(int $width = 100, int $height = 100): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 0));

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        $path = MediaService::LOGO_DIR . '/logo-teste.png';
        Storage::disk('replay')->put($path, $binary);

        return $path;
    }

    private function render(): Layout
    {
        return (new OverlayRenderer(new MediaService()))->render($this->layout->fresh());
    }

    public function test_layout_sem_logomarca_nao_gera_arquivo(): void
    {
        $layout = $this->render();

        // Vídeo limpo é uma escolha válida — e não pode virar um PNG preto.
        $this->assertNull($layout->overlay_path);
        $this->assertNull($layout->overlay_hash);
    }

    public function test_overlay_sai_no_tamanho_do_frame(): void
    {
        $this->layout->items()->create([
            'image_path' => $this->logo(),
            'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20,
        ]);

        $layout = $this->render();

        $this->assertNotNull($layout->overlay_path);
        Storage::disk('replay')->assertExists($layout->overlay_path);

        $size = getimagesizefromstring(Storage::disk('replay')->get($layout->overlay_path));

        $this->assertSame(1920, $size[0]);
        $this->assertSame(1080, $size[1]);
    }

    public function test_a_logo_cai_na_posicao_percentual_e_o_resto_fica_transparente(): void
    {
        $this->layout->items()->create([
            'image_path' => $this->logo(),
            'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20,
        ]);

        $layout = $this->render();
        $image = imagecreatefromstring(Storage::disk('replay')->get($layout->overlay_path));

        // 10% de 1920 = 192; 10% de 1080 = 108. O centro da peça cai em
        // (192 + 192, 108 + 108).
        $dentro = imagecolorsforindex($image, imagecolorat($image, 384, 216));
        $this->assertSame(255, $dentro['red']);
        $this->assertSame(0, $dentro['alpha'], 'A logo precisa sair opaca onde foi posicionada.');

        // Canto oposto: sem logo nenhuma, o pixel tem de estar VAZADO (127),
        // não preto opaco — a diferença entre um overlay e uma tarja.
        $fora = imagecolorsforindex($image, imagecolorat($image, 1800, 1000));
        $this->assertSame(127, $fora['alpha'], 'O que não tem logo precisa continuar transparente.');

        imagedestroy($image);
    }

    public function test_opacidade_reduz_o_alfa_da_peca(): void
    {
        $this->layout->items()->create([
            'image_path' => $this->logo(),
            'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50,
            'opacity' => 50,
        ]);

        $layout = $this->render();
        $image = imagecreatefromstring(Storage::disk('replay')->get($layout->overlay_path));

        $pixel = imagecolorsforindex($image, imagecolorat($image, 100, 100));

        $this->assertGreaterThan(0, $pixel['alpha']);
        $this->assertLessThan(127, $pixel['alpha']);

        imagedestroy($image);
    }

    public function test_hash_muda_com_a_posicao_e_o_arquivo_antigo_e_removido(): void
    {
        $item = $this->layout->items()->create([
            'image_path' => $this->logo(),
            'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20,
        ]);

        $primeiro = $this->render();
        $caminhoAntigo = $primeiro->overlay_path;

        $item->update(['x' => 60]);
        $segundo = $this->render();

        $this->assertNotSame($primeiro->overlay_hash, $segundo->overlay_hash);
        // URL nova a cada composição é o que dispensa invalidar cache do
        // outro lado; o arquivo anterior não pode ficar ocupando disco.
        Storage::disk('replay')->assertMissing($caminhoAntigo);
        Storage::disk('replay')->assertExists($segundo->overlay_path);
    }

    public function test_logomarca_sumida_do_disco_nao_derruba_a_composicao(): void
    {
        $this->layout->items()->create([
            'image_path' => 'replay/logos/que-nao-existe.png',
            'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20,
        ]);

        $layout = $this->render();

        // O overlay sai sem ela (e o problema vai para o log) em vez de a tela
        // de layouts quebrar inteira.
        $this->assertNotNull($layout->overlay_path);
        Storage::disk('replay')->assertExists($layout->overlay_path);
    }
}
