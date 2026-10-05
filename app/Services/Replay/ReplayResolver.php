<?php

namespace App\Services\Replay;

use App\Models\Place;
use App\Models\Replay\Camera;
use App\Models\Replay\Layout;
use App\Models\Replay\Setting;
use App\Support\Replay\Orientation;
use Illuminate\Support\Facades\DB;

/**
 * A cascata de herança do Replay, em um lugar só: QUADRA > ESPORTE > PADRÃO.
 *
 * Tanto as telas quanto a API consomem este resolvedor — nenhuma das duas
 * reimplementa a regra. É o que garante que o preview que o Marketing vê na
 * tela é exatamente o que a câmera vai receber.
 *
 * As tabelas de configuração e layout são pequenas (uma linha por esporte ou
 * quadra), então carregamos todas de uma vez e resolvemos em memória. Isso
 * evita o N+1 do endpoint que devolve todas as câmeras — justamente o mais
 * chamado da API.
 */
class ReplayResolver
{
    /** @var array<int, Setting>|null Configuração por place_id */
    private ?array $settingsByPlace = null;

    /** @var array<int, Setting>|null Configuração por place_group_id */
    private ?array $settingsByGroup = null;

    /** @var array<string, Layout>|null Layout por "place_id:orientacao" */
    private ?array $layoutsByPlace = null;

    /** @var array<string, Layout>|null Layout por "place_group_id:orientacao" */
    private ?array $layoutsByGroup = null;

    /**
     * Configuração efetiva de uma quadra.
     *
     * `source` diz de onde a configuração veio — a tela mostra "herdado do
     * esporte" com base nele, e é o que responde a pergunta "por que esta
     * quadra está gravando em pé?".
     *
     * @return array{orientation: string, clip_seconds: int, source: string, setting: ?Setting}
     */
    public function settingFor(Place $place): array
    {
        $this->loadSettings();

        $own = $this->settingsByPlace[$place->id] ?? null;
        if ($own && $own->active) {
            return [
                'orientation' => Orientation::normalize($own->orientation),
                'clip_seconds' => $this->clampSeconds($own->clip_seconds),
                'source' => 'place',
                'setting' => $own,
            ];
        }

        $inherited = $place->place_group_id ? ($this->settingsByGroup[$place->place_group_id] ?? null) : null;
        if ($inherited && $inherited->active) {
            return [
                'orientation' => Orientation::normalize($inherited->orientation),
                'clip_seconds' => $this->clampSeconds($inherited->clip_seconds),
                'source' => 'group',
                'setting' => $inherited,
            ];
        }

        // Nada configurado: a câmera ainda assim precisa gravar. O padrão é
        // deliberadamente conservador (horizontal, 30s) para que uma quadra
        // esquecida no cadastro não fique sem Replay.
        return [
            'orientation' => Orientation::DEFAULT_ORIENTATION,
            'clip_seconds' => Orientation::DEFAULT_CLIP_SECONDS,
            'source' => 'default',
            'setting' => null,
        ];
    }

    /**
     * Layout efetivo de uma quadra para a orientação dada. Devolve null
     * quando ninguém configurou logomarca — e aí o vídeo sai limpo.
     *
     * @return array{layout: ?Layout, source: string}
     */
    public function layoutFor(Place $place, string $orientation): array
    {
        $this->loadLayouts();

        $orientation = Orientation::normalize($orientation);

        $own = $this->layoutsByPlace[$place->id . ':' . $orientation] ?? null;
        if ($own && $own->active) {
            return ['layout' => $own, 'source' => 'place'];
        }

        $inherited = $place->place_group_id
            ? ($this->layoutsByGroup[$place->place_group_id . ':' . $orientation] ?? null)
            : null;

        if ($inherited && $inherited->active) {
            return ['layout' => $inherited, 'source' => 'group'];
        }

        return ['layout' => null, 'source' => 'none'];
    }

    /**
     * Tudo que uma câmera precisa saber, já resolvido.
     *
     * @return array{orientation: string, clip_seconds: int, setting_source: string, layout: ?Layout, layout_source: string}
     */
    public function resolveFor(Place $place): array
    {
        $setting = $this->settingFor($place);
        $layout = $this->layoutFor($place, $setting['orientation']);

        return [
            'orientation' => $setting['orientation'],
            'clip_seconds' => $setting['clip_seconds'],
            'setting_source' => $setting['source'],
            'layout' => $layout['layout'],
            'layout_source' => $layout['source'],
        ];
    }

    /**
     * Impressão digital de TODA a configuração publicada.
     *
     * O sistema de captura guarda este valor e só reprocessa quando ele muda
     * — é o que dispensa baixar o overlay de todas as quadras a cada
     * consulta. Entram as tabelas que afetam o que a câmera faz; o cadastro
     * de câmera entra porque incluir ou desativar uma muda a lista.
     */
    public function configHash(): string
    {
        $parts = [];

        foreach (['replay_settings', 'replay_layouts', 'replay_layout_items', 'replay_cameras'] as $table) {
            $row = DB::table($table)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as last_update')
                ->first();

            $parts[] = $table . ':' . ($row->total ?? 0) . ':' . ($row->last_update ?? '');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    /**
     * O teto de 60s é regra de negócio; aqui ela também protege o que já
     * está gravado no banco de uma configuração antiga fora da faixa.
     */
    private function clampSeconds(?int $seconds): int
    {
        $seconds = $seconds ?: Orientation::DEFAULT_CLIP_SECONDS;

        return max(Orientation::MIN_CLIP_SECONDS, min(Orientation::MAX_CLIP_SECONDS, $seconds));
    }

    private function loadSettings(): void
    {
        if ($this->settingsByPlace !== null) {
            return;
        }

        $settings = Setting::all();

        $this->settingsByPlace = $settings->whereNotNull('place_id')->keyBy('place_id')->all();
        $this->settingsByGroup = $settings->whereNotNull('place_group_id')->keyBy('place_group_id')->all();
    }

    private function loadLayouts(): void
    {
        if ($this->layoutsByPlace !== null) {
            return;
        }

        $layouts = Layout::with('items')->get();

        $this->layoutsByPlace = [];
        $this->layoutsByGroup = [];

        foreach ($layouts as $layout) {
            if ($layout->place_id) {
                $this->layoutsByPlace[$layout->place_id . ':' . $layout->orientation] = $layout;
            } elseif ($layout->place_group_id) {
                $this->layoutsByGroup[$layout->place_group_id . ':' . $layout->orientation] = $layout;
            }
        }
    }
}
