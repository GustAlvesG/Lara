<?php

namespace App\Http\Controllers\PoliBot;

use App\Http\Controllers\Controller;
use App\Models\BotAttendanceEvent as Evento;
use App\Models\BotSession;
use App\Services\Poli\PoliClient;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Bot WhatsApp › Transferências e encerramentos: o diário dos atendimentos
 * do O Lara (App\Services\PoliBot\AttendanceJournal), com o motivo de cada
 * transferência recebida, enviada e de cada encerramento.
 *
 * Só leitura. O período padrão é o dia de hoje; o resumo por motivo conta o
 * período inteiro, com os mesmos filtros da lista. Cada transferência enviada
 * mostra quem do time estava disponível naquele momento; o quadro "Na fila
 * agora" lista as conversas esperando na fila de espera.
 */
class AttendanceEventController extends Controller
{
    private const POR_PAGINA = 50;

    /**
     * O período mais longo de uma consulta. O diário não tem expurgo — fica
     * guardado para sempre; o limite é só do que a tela soma de uma vez.
     */
    private const MAX_DIAS = 366;

    public function index(Request $request, PoliClient $poli): View
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
            'tipo' => ['nullable', 'in:' . implode(',', array_keys(Evento::KINDS))],
            'motivo' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $ate = isset($filtros['ate']) ? Carbon::parse($filtros['ate']) : today();
        $de = isset($filtros['de']) ? Carbon::parse($filtros['de']) : $ate->copy();

        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate, $de];
        }

        if ($de->diffInDays($ate) > self::MAX_DIAS) {
            $de = $ate->copy()->subDays(self::MAX_DIAS);
        }

        $tipo = $filtros['tipo'] ?? null;
        $motivo = $filtros['motivo'] ?? null;

        $consulta = fn () => Evento::query()
            ->whereBetween('occurred_at', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->when($tipo, fn ($q) => $q->where('kind', $tipo))
            ->when(filled($filtros['q'] ?? null), function ($q) use ($filtros) {
                $busca = trim($filtros['q']);
                $digitos = preg_replace('/\D/', '', $busca);

                $q->where(function ($w) use ($busca, $digitos) {
                    $w->where('contact_name', 'like', "%{$busca}%")
                        ->orWhere('contact_uuid', $busca)
                        ->orWhere('attendance_uuid', $busca)
                        ->orWhere('actor', 'like', "%{$busca}%")
                        ->orWhere('outcome_detail', 'like', "%{$busca}%");

                    if (strlen($digitos) >= 4) {
                        $w->orWhere('contact_phone', 'like', "%{$digitos}%");
                    }
                });
            });

        // O resumo ignora o filtro de motivo: é dele que se escolhe um.
        $resumo = $consulta()
            ->selectRaw('kind, reason, COUNT(*) as total')
            ->groupBy('kind', 'reason')
            ->get()
            ->groupBy('kind')
            ->map(fn ($linhas, $kind) => $linhas
                ->map(fn ($l) => [
                    'reason' => $l->reason,
                    'label' => Evento::REASONS[$kind][$l->reason] ?? $l->reason,
                    'total' => (int) $l->total,
                ])
                ->sortByDesc('total')
                ->values());

        $resultados = $consulta()
            ->where('kind', Evento::ENVIADA)
            ->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome')
            ->map(fn ($n) => (int) $n);

        $eventos = $consulta()
            ->when($motivo, fn ($q) => $q->where('reason', $motivo))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Quem está esperando agora, independente do período e dos filtros.
        $fila = BotSession::where('state', BotSession::STATE_QUEUED)
            ->where('lara_owned', true)
            ->orderBy('queued_at')
            ->get();

        return view('poli-bot.events', [
            'fila' => $fila,
            'eventos' => $eventos,
            'resumo' => $resumo,
            'resultados' => $resultados,
            'times' => $this->teamNames($poli),
            'de' => $de,
            'ate' => $ate,
            'tipo' => $tipo,
            'motivo' => $motivo,
        ]);
    }

    /**
     * uuid do time => nome, para o destino dos transbordos. Do cache do
     * editor de fluxos (mesma chave), senão da API; sem a Poli, os times dos
     * fluxos padrão e o uuid para o resto.
     *
     * @return array<string, string>
     */
    private function teamNames(PoliClient $poli): array
    {
        $padrao = [
            DefaultFlows::TEAM_SECRETARIA => 'Secretaria',
            DefaultFlows::TEAM_FINANCEIRO => 'Financeiro',
            DefaultFlows::TEAM_ACHADOS => 'Achados e Perdidos',
        ];

        // A Poli fora do ar não pode deixar a tela lenta a cada abertura: a
        // falha fica lembrada por um minuto.
        if (Cache::has('poli-bot:teams:indisponivel')) {
            return $padrao;
        }

        try {
            $times = Cache::remember('poli-bot:teams', 600, fn () => array_values(array_map(
                fn (array $t) => ['uuid' => $t['uuid'] ?? null, 'name' => $t['attributes']['name'] ?? ($t['uuid'] ?? '?')],
                $poli->times(),
            )));
        } catch (Throwable) {
            Cache::put('poli-bot:teams:indisponivel', true, 60);

            return $padrao;
        }

        foreach ($times as $t) {
            if (is_string($t['uuid'] ?? null) && is_string($t['name'] ?? null)) {
                $padrao[$t['uuid']] = $t['name'];
            }
        }

        return $padrao;
    }
}
