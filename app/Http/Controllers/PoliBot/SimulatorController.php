<?php

namespace App\Http\Controllers\PoliBot;

use App\Http\Controllers\Controller;
use App\Services\PoliBot\BotSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * O simulador do editor. Cada pessoa tem a sua conversa simulada
 * ("painel-{id}"), e nada sai para a Poli — ver BotSimulator.
 */
class SimulatorController extends Controller
{
    public function __invoke(Request $request, BotSimulator $simulador): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:enviar,comecar,reiniciar'],
            'imagem' => ['nullable', 'boolean'],
            // Enviar pede texto — a não ser que seja a "imagem" simulada.
            'texto' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('acao') === 'enviar' && !$request->boolean('imagem'))],
            'fluxo' => ['nullable', 'string', 'max:60', 'required_if:acao,comecar'],
        ]);

        $contato = 'painel-' . ($request->user()->id ?? 'anonimo');

        return response()->json(match ($dados['acao']) {
            'reiniciar' => $this->reiniciar($simulador, $contato),
            'comecar' => $simulador->start($contato, $dados['fluxo']),
            default => ($dados['imagem'] ?? false)
                ? $simulador->send($contato, null, 'https://simulador.local/print.jpg')
                : $simulador->send($contato, $dados['texto']),
        });
    }

    private function reiniciar(BotSimulator $simulador, string $contato): array
    {
        $simulador->reset($contato);

        return ['replies' => [], 'session' => null];
    }
}
