<?php

namespace App\Http\Controllers\Replay\Web;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\Layout;
use App\Models\Replay\LayoutItem;
use App\Services\Replay\MediaService;
use App\Services\Replay\OverlayRenderer;
use App\Support\Replay\Orientation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Layouts de logomarca — a tela onde o Marketing monta o que aparece por cima
 * do vídeo.
 *
 * Toda alteração que muda o pixel final (logo nova, arrastar, redimensionar,
 * apagar) termina em uma re-renderização do overlay. É proposital: o arquivo
 * publicado nunca fica atrasado em relação ao que a tela mostra, e ninguém
 * precisa lembrar de apertar "gerar".
 */
class LayoutController extends Controller
{
    public function __construct(
        private OverlayRenderer $renderer,
        private MediaService $media,
    ) {
    }

    public function index()
    {
        $layouts = Layout::with(['group', 'place', 'items'])
            ->orderBy('place_group_id')
            ->orderBy('place_id')
            ->get();

        return view('replay.layouts.index', [
            'layouts' => $layouts,
            'ffmpegAvailable' => $this->ffmpegAvailable(),
        ]);
    }

    public function create()
    {
        return view('replay.layouts.create', [
            'groups' => PlaceGroup::orderBy('name')->get(),
            'places' => Place::with('group')->get(),
            'orientations' => Orientation::LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', 'in:group,place'],
            'owner_id' => ['required', 'integer'],
            'orientation' => ['required', 'in:' . implode(',', Orientation::ALL)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $key = $data['owner_type'] === 'group'
            ? ['place_group_id' => $data['owner_id'], 'place_id' => null]
            : ['place_id' => $data['owner_id'], 'place_group_id' => null];

        // A unicidade é (dono, orientação) e existe no banco. Conferir aqui
        // antes é o que transforma um erro 500 de constraint em uma mensagem
        // que a pessoa entende.
        $existing = Layout::where($key)->where('orientation', $data['orientation'])->first();

        if ($existing) {
            return redirect()
                ->route('replay.layouts.edit', $existing)
                ->with('success', 'Já existia um layout para este dono nesta orientação — abrimos ele para edição.');
        }

        $layout = Layout::create($key + [
            'orientation' => $data['orientation'],
            'name' => $data['name'],
            'active' => true,
        ]);

        return redirect()->route('replay.layouts.edit', $layout)
            ->with('success', 'Layout criado. Envie as logomarcas e posicione no preview.');
    }

    public function edit(Layout $layout)
    {
        $layout->load(['items', 'group', 'place']);

        return view('replay.layouts.edit', [
            'layout' => $layout,
            'dimensions' => $layout->dimensions(),
            'ffmpegAvailable' => $this->ffmpegAvailable(),
        ]);
    }

    public function update(Request $request, Layout $layout): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);

        // A orientação não se edita: ela é metade da chave única e define a
        // tela em que tudo foi posicionado. Trocar significaria reposicionar
        // todas as peças — melhor criar o layout do outro formato.
        $layout->update([
            'name' => $data['name'],
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Layout atualizado.');
    }

    public function destroy(Layout $layout): RedirectResponse
    {
        DB::transaction(function () use ($layout) {
            foreach ($layout->items as $item) {
                $this->media->remove($item->image_path);
            }

            $this->media->remove($layout->overlay_path);
            $this->media->remove($layout->overlay_animated_path);

            $layout->items()->delete();
            $layout->delete();
        });

        return redirect()->route('replay.layouts.index')
            ->with('success', 'Layout removido.');
    }

    /**
     * Sobe uma logomarca e a coloca no canto superior esquerdo, ocupando um
     * quinto da tela. Posição inicial previsível: a pessoa arrasta dali para
     * onde quiser, e uma logo nova nunca nasce escondida atrás de outra.
     */
    public function storeLogo(Request $request, Layout $layout): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'max:8192'],
        ], [], ['logo' => 'logomarca']);

        try {
            $stored = $this->media->storeLogo($request->file('logo'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        // Mantém a proporção original da imagem dentro da tela do layout:
        // uma logo larga não nasce esticada em um quadrado.
        $dimensions = $layout->dimensions();
        $width = 20.0;
        $height = $stored['width'] > 0 && $stored['height'] > 0
            ? round($width * ($stored['height'] / $stored['width']) * ($dimensions['width'] / $dimensions['height']), 3)
            : 20.0;

        $layout->items()->create([
            'image_path' => $stored['path'],
            'animated' => $stored['animated'],
            'x' => 2,
            'y' => 2,
            'width' => $width,
            'height' => min($height, 90),
            'opacity' => 100,
            'z_index' => (int) ($layout->items()->max('z_index') ?? 0) + 1,
        ]);

        $this->renderer->render($layout->fresh());

        return back()->with('success', 'Logomarca adicionada.');
    }

    /**
     * Recebe as posições vindas do editor (arrastar/redimensionar) e
     * re-renderiza.
     *
     * Responde JSON porque o editor salva sem recarregar a página — quem
     * acabou de arrastar cinco logos não quer perder o scroll.
     */
    public function updateItems(Request $request, Layout $layout): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.x' => ['required', 'numeric', 'between:-50,150'],
            'items.*.y' => ['required', 'numeric', 'between:-50,150'],
            'items.*.width' => ['required', 'numeric', 'between:0.5,200'],
            'items.*.height' => ['required', 'numeric', 'between:0.5,200'],
            'items.*.opacity' => ['required', 'integer', 'between:5,100'],
            'items.*.z_index' => ['required', 'integer', 'between:0,999'],
        ]);

        $owned = $layout->items()->pluck('id')->all();

        DB::transaction(function () use ($data, $owned) {
            foreach ($data['items'] as $item) {
                // Só o que pertence a ESTE layout: o id vem do navegador.
                if (! in_array($item['id'], $owned, true)) {
                    continue;
                }

                LayoutItem::where('id', $item['id'])->update([
                    'x' => $item['x'],
                    'y' => $item['y'],
                    'width' => $item['width'],
                    'height' => $item['height'],
                    'opacity' => $item['opacity'],
                    'z_index' => $item['z_index'],
                ]);
            }
        });

        $layout = $this->renderer->render($layout->fresh());

        return response()->json([
            'ok' => true,
            'overlay_url' => $layout->overlayUrl(),
            'animated_url' => $layout->animatedOverlayUrl(),
            'rendered_at' => $layout->overlay_rendered_at?->format('d/m/Y H:i'),
        ]);
    }

    public function destroyItem(Layout $layout, LayoutItem $item): RedirectResponse
    {
        abort_unless($item->replay_layout_id === $layout->id, 404);

        $this->media->remove($item->image_path);
        $item->delete();

        $this->renderer->render($layout->fresh());

        return back()->with('success', 'Logomarca removida.');
    }

    /** Re-renderiza sob demanda — socorro para quando o ffmpeg estava fora do ar. */
    public function rerender(Layout $layout): RedirectResponse
    {
        $this->renderer->render($layout);

        return back()->with('success', 'Overlay gerado novamente.');
    }

    /**
     * O overlay animado depende de ffmpeg, que pode não estar instalado. A
     * tela avisa em vez de deixar o Marketing subir um GIF e descobrir depois
     * do jogo que ele saiu parado.
     */
    private function ffmpegAvailable(): bool
    {
        // Em cache: subir um processo a cada carregamento de tela custa mais
        // do que a resposta vale. Dez minutos é curto o bastante para a tela
        // reconhecer o ffmpeg logo depois de a operação instalar.
        return \Illuminate\Support\Facades\Cache::remember('replay:ffmpeg-available', 600, function () {
            $binary = (string) config('services.replay.ffmpeg', 'ffmpeg');

            try {
                return \Illuminate\Support\Facades\Process::timeout(10)
                    ->run([$binary, '-version'])
                    ->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
