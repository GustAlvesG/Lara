<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchInformationRequest;
use App\Models\DataInfo;
use App\Support\KeywordSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Busca por palavra-chave nas informações do mural.
 *
 * O conteúdo em si mora em `data_infos`: cada edição grava uma linha nova
 * apontando para o mesmo `information_id`, então `information` é só o
 * agrupador (id, privacidade, autor) e a versão vigente é a última linha de
 * cada grupo — é essa que o painel exibe (veja InformationController::index).
 */
class InformationSearchController extends Controller
{
    /**
     * Campos varridos pela palavra-chave, com o peso de cada um no
     * ranqueamento por relevância — casar no nome pesa mais que casar só na
     * descrição, então "Aula de Natação" fica acima de um registro que só
     * menciona "natação" de passagem no texto.
     */
    private const WEIGHTS = [
        'name' => 3,
        'category' => 2,
        'description' => 1,
    ];

    /**
     * Peso de um acerto em tag. As tags substituíram o campo `category`, que
     * segue sendo varrido só por causa dos registros antigos — versões novas
     * gravam as categorias como tags (ver a migration data_info_tag).
     */
    private const TAG_WEIGHT = 2;

    /**
     * GET /api/information/search?q=frase em linguagem natural
     *
     * A frase é reduzida a palavras-chave (SearchInformationRequest::keywords,
     * que descarta artigos/preposições/pronomes via config/search.php) e cada
     * termo é buscado com OR — resultado é ranqueado por quantos termos e em
     * quais campos casaram, não só "contém ou não contém".
     *
     * Por padrão a busca considera apenas a versão vigente de cada informação.
     * Com `all_versions=1` o histórico inteiro entra na varredura e cada versão
     * que casar volta como um item próprio.
     */
    public function index(SearchInformationRequest $request): JsonResponse
    {
        $keyword = $request->keyword();
        $terms = $request->keywords();

        $query = DataInfo::with(['information', 'user:id,name', 'tags:id,name'])
            // Descarta o que pertence a uma informação apagada (soft delete).
            ->whereHas('information')
            ->where(function (Builder $q) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    foreach (array_keys(self::WEIGHTS) as $column) {
                        $q->orWhere($column, 'like', $like);
                    }
                    $q->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like));
                }
            });

        if (!$request->boolean('all_versions')) {
            $query->whereIn('id', $this->currentVersionIds());
        }

        [$relevanceSql, $relevanceBindings] = KeywordSearch::relevance(
            $terms,
            self::WEIGHTS,
            'SELECT 1 FROM data_info_tag dit '
                . 'INNER JOIN tags tg ON tg.id = dit.tag_id '
                . 'WHERE dit.data_info_id = data_infos.id AND tg.name LIKE ?',
            self::TAG_WEIGHT,
        );

        $results = $query
            ->selectRaw("data_infos.*, ({$relevanceSql}) as relevance", $relevanceBindings)
            ->orderByDesc('relevance')
            // Empate em relevância: o mais recente vence. id desc é o
            // desempate final — created_at é único por linha na prática, mas
            // o id garante ordem determinística mesmo se algum dia colidir.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $results->each(function (DataInfo $info) {
            $info->relevance = (int) $info->relevance;
            $info->description = KeywordSearch::stripHtml($info->description);
        });

        return response()->json([
            'keyword'      => $keyword,
            'terms'        => $terms,
            'all_versions' => $request->boolean('all_versions'),
            'total'        => $results->count(),
            'data'         => $results->values(),
        ]);
    }

    /**
     * Ids da última versão de cada informação. O id cresce junto com
     * created_at (toda edição é um INSERT), então MAX(id) é a versão vigente e
     * não empata como created_at empataria em edições no mesmo segundo.
     */
    private function currentVersionIds(): Builder
    {
        return DataInfo::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('information_id');
    }
}
