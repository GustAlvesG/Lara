<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favoritos e ordem do menu da pessoa logada, gravados na conta para não
 * dependerem do navegador (ver a migration `add_nav_preferences_to_users`).
 *
 * O layout chama a cada mudança (estrela, arrastar, organizar). Só guarda
 * chaves: o que cada chave abre continua decidido pelo menu e pelas
 * permissões — favorito de tela que a pessoa perdeu simplesmente não aparece.
 */
class NavPreferencesController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'favorites' => ['present', 'array', 'max:60'],
            'favorites.*' => ['string', 'max:120'],
            'order' => ['present', 'array', 'max:60'],
            'order.*' => ['string', 'max:120'],
        ]);

        $preferences = [
            'favorites' => array_values(array_unique($data['favorites'])),
            'order' => array_values(array_unique($data['order'])),
        ];

        $request->user()->forceFill(['nav_preferences' => $preferences])->save();

        return response()->json($preferences);
    }
}
