<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Replay\Camera;
use App\Models\Replay\Video;
use App\Support\Replay\Orientation;
use App\View\Navigation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * O Replay (branch `repet`) dentro do layout do rebrand: as quatro telas abrem
 * no shell novo, as abas entre elas vêm da capa da área (o partial de abas
 * próprio do módulo saiu) e o grupo do menu obedece à permissão `replay` do
 * catálogo — não mais à `manage replay` do Spatie.
 *
 * Listas vazias, sem banco; usuário mock.
 */
class ReplayScreensTest extends TestCase
{
    use RendersScreens;

    private function quemConfigura()
    {
        return $this->usuario(new UserAccess([P::REPLAY]));
    }

    public function test_as_quatro_telas_abrem_no_layout_novo_com_as_abas_da_capa(): void
    {
        $telas = [
            'replay.settings.index' => ['replay.settings.index', [
                'rows' => collect(),
                'orientations' => Orientation::LABELS,
                'minSeconds' => Orientation::MIN_CLIP_SECONDS,
                'maxSeconds' => Orientation::MAX_CLIP_SECONDS,
            ]],
            'replay.layouts.index' => ['replay.layouts.index', [
                'layouts' => collect(),
                'ffmpegAvailable' => false,
            ]],
            'replay.cameras.index' => ['replay.cameras.index', [
                'cameras' => collect(),
                'resolved' => collect(),
                'places' => collect(),
                'maxPerPlace' => Camera::MAX_PER_PLACE,
            ]],
            'replay.videos.index' => ['replay.videos.index', [
                'videos' => new LengthAwarePaginator([], 0, 20),
                'groups' => collect(),
                'places' => collect(),
                'retentionDays' => Video::RETENTION_DAYS,
            ]],
        ];

        foreach ($telas as $rota => [$view, $data]) {
            $html = $this->tela($this->quemConfigura(), $rota, [], $view, $data);

            $this->assertStringContainsString('aria-label="Páginas de Replay"', $html, $rota);
            $this->assertMatchesRegularExpression('#href="' . preg_quote(route($rota), '#') . '"\s+aria-current="page"#', $html, $rota);

            foreach (array_keys($telas) as $irma) {
                $this->assertStringContainsString('href="' . route($irma) . '"', $html, "{$rota} sem a aba {$irma}");
            }
        }
    }

    public function test_menu_mostra_o_replay_so_para_quem_tem_a_permissao(): void
    {
        $request = Request::create('/dashboard');

        $com = Navigation::build($this->quemConfigura(), $request);
        $sem = Navigation::build($this->usuario(new UserAccess([])), $request);

        $grupo = collect($com['links'])->firstWhere('label', 'Replay');
        $this->assertNotNull($grupo);
        $this->assertSame('replay.settings.index', $grupo['route']);
        $this->assertCount(4, $grupo['children']);

        $this->assertNull(collect($sem['links'])->firstWhere('label', 'Replay'));
    }

    public function test_replay_esta_no_catalogo_e_no_de_para_da_permissao_antiga(): void
    {
        $this->assertTrue(P::exists(P::REPLAY));
        $this->assertSame('Replay', P::group(P::REPLAY));
        $this->assertSame([P::REPLAY], \App\Authorization\LegacyPermissionMap::PERMISSIONS['manage replay']);
    }
}
