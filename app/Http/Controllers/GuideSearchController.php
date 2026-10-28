<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchGuideRequest;
use App\Services\Guides\GuideSearch;
use Illuminate\Http\JsonResponse;

/**
 * Busca nos guias do usuário (docs/guias), consumida pela Lara (IA) para
 * ensinar o funcionário a usar O Lara. Ver GuideSearch e
 * docs/funcionalidades/lara-ia-consultas.md.
 */
class GuideSearchController extends Controller
{
    /**
     * GET /api/guias/search?q=como emitir cortesia&user_id=portal_12[&modulo=Ingressos][&limit=6]
     */
    public function index(SearchGuideRequest $request, GuideSearch $search): JsonResponse
    {
        $user = $request->asker();

        $result = $search->search($request->keywords(), $user, $request->modulo(), $request->limit());

        return response()->json([
            'keyword' => $request->keyword(),
            'terms'   => $result['terms'],
            'user_id' => $user->id,
            'modulo'  => $request->modulo(),
            'total'   => count($result['data']),
            'data'    => $result['data'],
        ]);
    }
}
