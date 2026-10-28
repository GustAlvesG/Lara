<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvisoRequest;
use App\Models\Aviso;
use App\Support\KeywordSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Busca por palavra-chave nos avisos do InfoClube, em nome de um funcionário.
 *
 * Diferente das informações, aviso tem privacidade: a busca só devolve o que
 * foi DIRIGIDO a quem pergunta (Aviso::scopeAddressedTo — público, setor,
 * grupo, pessoal). Não usa scopeVisibleTo de propósito: na tela, quem tem
 * acesso total enxerga todo aviso do sistema, mas a IA não deve repetir para
 * a Gerência o aviso pessoal de outra pessoa só porque ela pode abri-lo.
 */
class AvisoSearchController extends Controller
{
    /** Peso de cada campo no ranqueamento, como na busca de informações. */
    private const WEIGHTS = [
        'title' => 3,
        'content' => 1,
    ];

    private const TAG_WEIGHT = 2;

    /**
     * GET /api/avisos/search?q=frase&user_id=portal_12
     *
     * Por padrão só avisos ativos (sem validade ou com validade de hoje em
     * diante). Com `include_expired=1` os vencidos também entram, marcados
     * com `expired: true`.
     */
    public function index(SearchAvisoRequest $request): JsonResponse
    {
        $user = $request->asker();
        $terms = $request->keywords();

        $query = Aviso::with(['creator:id,name', 'tags:id,name'])
            ->addressedTo($user)
            ->where(function (Builder $q) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    foreach (array_keys(self::WEIGHTS) as $column) {
                        $q->orWhere($column, 'like', $like);
                    }
                    $q->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like));
                }
            });

        if (!$request->boolean('include_expired')) {
            $query->active();
        }

        [$relevanceSql, $relevanceBindings] = KeywordSearch::relevance(
            $terms,
            self::WEIGHTS,
            'SELECT 1 FROM aviso_tag avt '
                . 'INNER JOIN tags tg ON tg.id = avt.tag_id '
                . 'WHERE avt.aviso_id = avisos.id AND tg.name LIKE ?',
            self::TAG_WEIGHT,
        );

        $results = $query
            ->selectRaw("avisos.*, ({$relevanceSql}) as relevance", $relevanceBindings)
            ->orderByDesc('relevance')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Lista explícita de campos: o model carrega também os destinatários,
        // as visualizações e as ciências, que não são da conta de quem pergunta.
        $data = $results->map(fn (Aviso $aviso) => [
            'id'         => $aviso->id,
            'title'      => $aviso->title,
            'content'    => KeywordSearch::stripHtml($aviso->content),
            'privacy'    => $aviso->privacy,
            'mandatory'  => (bool) $aviso->mandatory,
            'expires_at' => $aviso->expires_at?->toDateString(),
            'expired'    => $aviso->isExpired(),
            'created_at' => $aviso->created_at?->toIso8601String(),
            'updated_at' => $aviso->updated_at?->toIso8601String(),
            'author'     => $aviso->creator?->name,
            'tags'       => $aviso->tags->pluck('name')->values(),
            'url'        => route('avisos.show', $aviso),
            'relevance'  => (int) $aviso->relevance,
        ]);

        return response()->json([
            'keyword'         => $request->keyword(),
            'terms'           => $terms,
            'user_id'         => $user->id,
            'include_expired' => $request->boolean('include_expired'),
            'total'           => $data->count(),
            'data'            => $data->values(),
        ]);
    }
}
