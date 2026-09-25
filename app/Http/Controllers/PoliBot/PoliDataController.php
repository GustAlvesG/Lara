<?php

namespace App\Http\Controllers\PoliBot;

use App\Http\Controllers\Controller;
use App\Services\Poli\PoliClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Templates e times da conta Poli, para as listas do editor.
 *
 * Em cache por 10 minutos: o editor abre e reabre, e cada abertura não
 * precisa gastar cota da API. `?atualizar=1` pula o cache — é o botão
 * "recarregar" de quem acabou de criar um template no painel da Poli.
 */
class PoliDataController extends Controller
{
    private const TTL = 600;

    public function templates(Request $request, PoliClient $poli): JsonResponse
    {
        return $this->doCache($request, 'poli-bot:templates', fn () => array_values(array_map(
            fn (array $t) => $this->template($t),
            $poli->templates(),
        )));
    }

    public function teams(Request $request, PoliClient $poli): JsonResponse
    {
        return $this->doCache($request, 'poli-bot:teams', fn () => array_values(array_map(
            fn (array $t) => ['uuid' => $t['uuid'] ?? null, 'name' => $t['attributes']['name'] ?? ($t['uuid'] ?? '?')],
            $poli->times(),
        )));
    }

    private function doCache(Request $request, string $chave, callable $buscar): JsonResponse
    {
        if ($request->boolean('atualizar')) {
            Cache::forget($chave);
        }

        try {
            return response()->json(['data' => Cache::remember($chave, self::TTL, $buscar)]);
        } catch (Throwable $e) {
            Log::warning('PoliBot: editor não conseguiu ler a Poli', ['chave' => $chave, 'erro' => $e->getMessage()]);

            return response()->json([
                'message' => 'Não foi possível consultar a Poli agora. Você ainda pode colar o uuid à mão.',
            ], 502);
        }
    }

    /**
     * O que o editor precisa de um template: identificação, o texto, e as
     * opções — linhas da lista (LIST) ou botões (BUTTON/WABA) — para montar o
     * menu do passo sem digitar de novo.
     */
    private function template(array $t): array
    {
        $msg = $t['message'] ?? null;
        $opcoes = [];

        if (is_array($msg)) {
            foreach ($msg['section'] ?? $msg['sections'] ?? [] as $secao) {
                foreach ($secao['rows'] ?? [] as $linha) {
                    $opcoes[] = [
                        'label' => $linha['messageOption']['title'] ?? $linha['title'] ?? '',
                        'description' => $linha['messageOption']['description'] ?? $linha['description'] ?? '',
                    ];
                }
            }

            foreach ($msg['buttons'] ?? [] as $botao) {
                $opcoes[] = ['label' => $botao['text'] ?? '', 'description' => ''];
            }
        }

        return [
            'uuid' => $t['uuid'] ?? null,
            'key' => $t['key'] ?? '',
            'type' => $t['type'] ?? '',
            'status' => $t['status'] ?? '',
            'body' => is_array($msg) ? (string) ($msg['body'] ?? '') : (string) $msg,
            'options' => array_values(array_filter($opcoes, fn ($o) => $o['label'] !== '')),
        ];
    }
}
