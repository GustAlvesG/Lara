<?php

namespace App\Http\Controllers\Replay\Web;

use App\Http\Controllers\Controller;
use App\Services\Replay\RepetClient;
use App\Services\Replay\RepetUnavailable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * RePET › Transmissões: ligar e desligar a live de cada quadra no YouTube e
 * operar o placar. Tudo é repassado ao servidor do rePET (RepetClient); o
 * Lara não guarda estado nenhum — a verdade é o `/status` do servidor, que a
 * tela consulta de poucos em poucos segundos.
 *
 * As ações respondem JSON: {ok, message?, state?}. Erro do servidor vira
 * 422/502 com a mensagem pronta para a tela. Ligar, desligar e mudança de
 * modo vão para o log com quem fez — publicam vídeo para o público.
 */
class BroadcastController extends Controller
{
    public function __construct(private RepetClient $repet)
    {
    }

    public function index()
    {
        return view('replay.broadcasts.index', [
            'enabled' => $this->repet->enabled(),
        ]);
    }

    public function status(): JsonResponse
    {
        return $this->call(fn () => ['state' => $this->repet->status()]);
    }

    /** Liga com placar (padrão) ou direta (`modo=direta`, sem placar). */
    public function start(Request $request, string $court): JsonResponse
    {
        $data = $request->validate([
            'modo' => ['required', Rule::in(['placar', 'direta'])],
            'nome_a' => ['nullable', 'string', 'max:40'],
            'nome_b' => ['nullable', 'string', 'max:40'],
            'reset' => ['nullable', 'boolean'],
        ]);

        return $this->call(function () use ($data, $court) {
            if ($data['modo'] === 'direta') {
                $result = $this->repet->startLive($court);
            } else {
                $result = $this->repet->startBroadcast($court, [
                    'nome_a' => $data['nome_a'] ?? null,
                    'nome_b' => $data['nome_b'] ?? null,
                    'reset' => (bool) ($data['reset'] ?? true),
                ]);
            }

            $this->audit('ligou', $court, $data['modo'], $result['status'] ?? null);

            return [
                'state' => $result,
                'message' => ($result['status'] ?? null) === 'ja_no_ar'
                    ? 'A quadra já estava no ar; nada foi alterado.'
                    : 'Transmissão ligada. A imagem leva alguns segundos para aparecer no YouTube.',
            ];
        });
    }

    public function stop(Request $request, string $court): JsonResponse
    {
        $data = $request->validate([
            'modo' => ['required', Rule::in(['placar', 'direta'])],
        ]);

        return $this->call(function () use ($data, $court) {
            $result = $data['modo'] === 'direta'
                ? $this->repet->stopLive($court)
                : $this->repet->stopBroadcast($court);

            $this->audit('desligou', $court, $data['modo'], $result['status'] ?? null);

            return [
                'state' => $result,
                'message' => ($result['status'] ?? null) === 'nao_estava_no_ar'
                    ? 'A quadra não estava no ar.'
                    : 'Transmissão encerrada.',
            ];
        });
    }

    public function point(Request $request, string $court): JsonResponse
    {
        $data = $this->sideAndDelta($request);

        return $this->call(fn () => ['state' => $this->repet->point($court, $data['lado'], $data['delta'])]);
    }

    public function set(Request $request, string $court): JsonResponse
    {
        $data = $this->sideAndDelta($request);

        return $this->call(fn () => ['state' => $this->repet->set($court, $data['lado'], $data['delta'])]);
    }

    /** Correção manual: grava o placar exato (campo vazio não muda). */
    public function score(Request $request, string $court): JsonResponse
    {
        $data = $request->validate([
            'pontos_a' => ['nullable', 'integer', 'min:0', 'max:999'],
            'pontos_b' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sets_a' => ['nullable', 'integer', 'min:0', 'max:99'],
            'sets_b' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $score = array_map('intval', array_filter($data, fn ($v) => $v !== null));

        return $this->call(fn () => [
            'state' => $this->repet->putScore($court, $score),
            'message' => 'Placar corrigido.',
        ]);
    }

    public function reset(string $court): JsonResponse
    {
        return $this->call(fn () => [
            'state' => $this->repet->resetScore($court),
            'message' => 'Placar zerado.',
        ]);
    }

    public function names(Request $request, string $court): JsonResponse
    {
        $data = $request->validate([
            'nome_a' => ['nullable', 'string', 'max:40'],
            'nome_b' => ['nullable', 'string', 'max:40'],
        ]);

        $names = array_filter($data, fn ($v) => $v !== null);

        return $this->call(fn () => [
            'state' => $this->repet->names($court, $names),
            'message' => 'Nomes atualizados.',
        ]);
    }

    public function volume(Request $request, string $court): JsonResponse
    {
        $data = $request->validate([
            'nivel' => ['required', 'integer', 'min:0', 'max:400'],
        ]);

        return $this->call(fn () => ['state' => $this->repet->volume($court, (int) $data['nivel'])]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function sideAndDelta(Request $request): array
    {
        $data = $request->validate([
            'lado' => ['required', Rule::in(RepetClient::SIDES)],
            'delta' => ['nullable', 'integer', Rule::in([-1, 1])],
        ]);

        return ['lado' => $data['lado'], 'delta' => (int) ($data['delta'] ?? 1)];
    }

    /** @param  Closure(): array  $action */
    private function call(Closure $action): JsonResponse
    {
        if (! $this->repet->enabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'A integração com o servidor do rePET está desligada. Avise a TI (REPET_API_URL / REPET_API_TOKEN).',
            ], 503);
        }

        try {
            return response()->json(['ok' => true] + $action());
        } catch (RepetUnavailable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'uncertain' => $e->uncertain,
            ], $e->status !== null && $e->status < 500 ? 422 : 502);
        }
    }

    private function audit(string $what, string $court, string $mode, ?string $status): void
    {
        Log::info("RePET: {$what} a transmissão", [
            'quadra' => $court,
            'modo' => $mode,
            'status' => $status,
            'user_id' => auth()->id(),
        ]);
    }
}
