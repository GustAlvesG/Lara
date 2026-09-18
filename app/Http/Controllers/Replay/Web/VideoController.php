<?php

namespace App\Http\Controllers\Replay\Web;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Replay\Video;
use App\Services\Replay\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Galeria interna dos clipes — o que o Marketing usa para achar material e
 * para conferir se as quadras estão gravando.
 *
 * Mostra inclusive os já vencidos que o expurgo ainda não varreu, com a
 * contagem de dias restantes: é a tela onde se descobre que uma câmera parou
 * de mandar antes de o sócio reclamar.
 */
class VideoController extends Controller
{
    const PER_PAGE = 24;

    public function __construct(private MediaService $media)
    {
    }

    public function index(Request $request)
    {
        $videos = Video::with(['place.group', 'camera', 'member'])
            ->when($request->filled('place_group_id'), fn ($q) => $q->where('place_group_id', $request->input('place_group_id')))
            ->when($request->filled('place_id'), fn ($q) => $q->where('place_id', $request->input('place_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('recorded_at', $request->input('date')))
            ->when($request->input('linked') === 'yes', fn ($q) => $q->whereNotNull('member_id'))
            ->when($request->input('linked') === 'no', fn ($q) => $q->whereNull('member_id'))
            ->orderByDesc('recorded_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('replay.videos.index', [
            'videos' => $videos,
            'groups' => PlaceGroup::orderBy('name')->get(),
            'places' => Place::with('group')->get(),
            'retentionDays' => Video::RETENTION_DAYS,
        ]);
    }

    /**
     * Exclusão manual, para quando alguém aperta o botão por engano ou grava
     * algo que não deveria ficar no ar. Apaga o arquivo junto: deixar o
     * arquivo com a linha removida é exatamente o órfão que o expurgo não
     * pega mais.
     */
    public function destroy(Video $video): RedirectResponse
    {
        $this->media->remove($video->file_path);
        $video->delete();

        return back()->with('success', 'Vídeo removido.');
    }
}
