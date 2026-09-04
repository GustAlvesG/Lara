<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Models\FreelancerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API do jantar dos freelancers, consumida pela cozinha.
 *
 * Responde a uma pergunta só: quem confirmou o jantar de um determinado dia.
 * Devolve apenas os "sim" — quem disse "não" e quem sequer foi perguntado não
 * viram prato, e uma lista que misturasse os três obrigaria a cozinha a filtrar
 * o que já é resposta.
 *
 * A data consultada é a do JANTAR (`dinner_date`), não a de início do contrato:
 * um turno que começa 22:00 e vira a meia-noite janta no dia seguinte, e é no
 * dia em que come que ele precisa aparecer.
 *
 * A rota é ABERTA, sem `api_token` (ver `routes/api.php`). Por isso o CPF fica
 * de fora do payload: para servir o prato basta o nome, e uma lista aberta não
 * é lugar de documento de ninguém. Quem precisar cruzar com o cadastro usa o
 * `freelancer_id`, que só serve dentro do sistema.
 */
class DinnerApiController extends Controller
{
    /** Confirmações de jantar de um dia. Sem `date`, o dia de hoje. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();

        $services = FreelancerService::dinnerConfirmedOn($date)
            ->with(['freelancer', 'functionFreelancer'])
            ->get()
            // Ordena pelo nome porque a lista é lida na cozinha, em voz alta,
            // conferindo quem chega — não é um relatório para arquivo.
            ->sortBy(fn (FreelancerService $service) => mb_strtolower((string) $service->freelancer?->name))
            ->values();

        return response()->json([
            'ok' => true,
            'date' => $date->toDateString(),
            'window' => FreelancerService::dinnerWindowLabel(),
            'total' => $services->count(),
            'dinners' => $services->map(fn (FreelancerService $service) => $this->dinnerPayload($service)),
        ]);
    }

    private function dinnerPayload(FreelancerService $service): array
    {
        return [
            'service_id' => $service->id,
            'freelancer_id' => $service->freelancer_id,
            'name' => $service->freelancer?->name,
            'function' => $service->functionFreelancer?->name,
            'location' => $service->location,
            'shift_date' => $service->start_date?->toDateString(),
            'start_time' => substr((string) $service->start_time, 0, 5),
            'end_time' => substr((string) $service->end_time, 0, 5),
            // O turno vira a meia-noite: o `end_time` é do dia seguinte, e quem
            // lê a lista precisa saber disso sem ter de comparar as duas datas.
            'crosses_midnight' => $service->start_date && $service->end_date
                ? $service->start_date->ne($service->end_date)
                : false,
            'duration' => $service->formattedDuration(),
            'duration_minutes' => $service->durationInMinutes(),
            'answered_at' => $service->dinner_answered_at?->toIso8601String(),
        ];
    }
}
