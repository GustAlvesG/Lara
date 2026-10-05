<?php

namespace App\Services;

use App\Models\Company\CompanyAccessLog;
use App\Models\UberAccessRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SIV — busca de placa: liga a leitura da câmera (`parkings`) aos acessos de
 * externos registrados na portaria e aos pedidos de carro de aplicativo.
 *
 * Os associados vêm das catracas do MultiClubes, por AccessController. Aqui
 * fica o que mora no banco local:
 *
 *   - terceirizado, freelancer e liberação pontual: `company_access_logs`.
 *     O registro não guarda placa, então a ligação é só pelo horário.
 *   - carro de aplicativo: `uber_access_requests`. O pedido tem a placa, então
 *     a ligação é exata; o horário só decide a qual leitura do dia ele pertence.
 */
class ParkingAccessCorrelationService
{
    /**
     * Folga entre a câmera ler a placa e a portaria registrar o externo.
     * Medido nos acessos de Uber de set/2026, que têm placa dos dois lados:
     * ±15s (a janela das catracas) pega 64% e ±60s pega 89%.
     */
    public const EXTERNAL_WINDOW_SECONDS = 60;

    /**
     * Distância máxima entre a liberação do carro de aplicativo e a leitura
     * da mesma placa. Larga porque a placa já confere: só evita pendurar o
     * pedido da manhã na leitura da tarde.
     */
    public const APP_CAR_WINDOW_SECONDS = 1800;

    public const KIND_WORKER = 'terceirizado';
    public const KIND_FREELANCER = 'freelancer';
    public const KIND_ONE_OFF = 'pontual';
    public const KIND_APP_CAR = 'aplicativo';

    public const KIND_LABELS = [
        self::KIND_WORKER => 'Terceirizado',
        self::KIND_FREELANCER => 'Freelancer',
        self::KIND_ONE_OFF => 'Liberação pontual',
        self::KIND_APP_CAR => 'Carro de aplicativo',
    ];

    /**
     * Externos registrados na portaria em torno do horário da leitura.
     *
     * Entra também o registro negado: a pessoa estava na portaria naquele
     * instante, e a tela marca que não foi liberada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function externalsAround($entryDate): array
    {
        $at = $this->parseEntryDate($entryDate);

        if ($at === null) {
            return [];
        }

        $logs = CompanyAccessLog::query()
            ->with(['worker' => fn ($q) => $q->withTrashed(), 'company', 'freelancer', 'oneOffAccess'])
            ->whereBetween('created_at', [
                $at->copy()->subSeconds(self::EXTERNAL_WINDOW_SECONDS),
                $at->copy()->addSeconds(self::EXTERNAL_WINDOW_SECONDS),
            ])
            ->where(function ($q) {
                $q->whereNotNull('company_worker_id')
                    ->orWhereNotNull('freelancer_id')
                    ->orWhereNotNull('one_off_access_id');
            })
            ->orderBy('created_at')
            ->get();

        return $logs
            ->map(fn (CompanyAccessLog $log) => $this->externalRow($log))
            ->filter()
            // A mesma pessoa registrada duas vezes na janela aparece uma vez,
            // e a liberada vence a negada.
            ->sortByDesc('allowed')
            ->unique('key')
            ->sortBy('time')
            ->values()
            ->all();
    }

    /**
     * Pedidos de carro de aplicativo da placa no dia: os feitos no dia e os
     * liberados no dia (um pedido pode ser feito na véspera).
     *
     * @return Collection<int, UberAccessRequest>
     */
    public function appCarRequests(string $plate, $startOfDay, $endOfDay): Collection
    {
        $plate = $this->normalizePlate($plate);

        if ($plate === '') {
            return collect();
        }

        $range = [Carbon::parse($startOfDay), Carbon::parse($endOfDay)];

        return UberAccessRequest::query()
            ->where('vehicle_plate', $plate)
            ->where(function ($q) use ($range) {
                $q->whereBetween('created_at', $range)
                    ->orWhereBetween('accessed_at', $range);
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Distribui os pedidos liberados entre as leituras do dia: cada pedido vai
     * para a leitura mais próxima da liberação, dentro da janela. Pedido sem
     * liberação, ou liberado longe de qualquer leitura, não entra em nenhuma —
     * continua aparecendo na lista de pedidos da placa.
     *
     * @param  array<int|string, mixed>  $entryDates  horário da leitura por chave
     * @return array<int|string, array<int, array<string, mixed>>>  linhas por chave
     */
    public function appCarsByEntry(array $entryDates, Collection $requests): array
    {
        $entries = array_filter(array_map(fn ($date) => $this->parseEntryDate($date), $entryDates));
        $byEntry = [];

        foreach ($requests as $request) {
            if ($request->accessed_at === null) {
                continue;
            }

            $closest = null;
            $distance = null;

            foreach ($entries as $key => $at) {
                $seconds = abs($at->getTimestamp() - $request->accessed_at->getTimestamp());

                if ($seconds <= self::APP_CAR_WINDOW_SECONDS && ($distance === null || $seconds < $distance)) {
                    $closest = $key;
                    $distance = $seconds;
                }
            }

            if ($closest !== null) {
                $byEntry[$closest][] = $this->appCarRow($request);
            }
        }

        return $byEntry;
    }

    /** Linha da tabela de pessoas do acesso, para um pedido de carro de aplicativo. */
    public function appCarRow(UberAccessRequest $request): array
    {
        return [
            'key' => self::KIND_APP_CAR . ':' . $request->id,
            'kind' => self::KIND_APP_CAR,
            'label' => self::KIND_LABELS[self::KIND_APP_CAR],
            'ident' => $request->matricula ? 'Mat./CPF ' . $request->matricula : null,
            'name' => $request->requester_name ?: 'Pedido sem nome',
            'detail' => $request->club_location ? 'Pedido para ' . $request->club_location : 'Pedido de carro de aplicativo',
            'telephone' => $request->contact_phone,
            'time' => optional($request->accessed_at)->format('H:i:s'),
            'allowed' => true,
        ];
    }

    private function externalRow(CompanyAccessLog $log): ?array
    {
        $base = [
            'time' => $log->created_at->format('H:i:s'),
            'allowed' => (bool) $log->allowed,
        ];

        if ($log->company_worker_id !== null) {
            $worker = $log->worker;

            return $base + [
                'key' => self::KIND_WORKER . ':' . $log->company_worker_id,
                'kind' => self::KIND_WORKER,
                'label' => self::KIND_LABELS[self::KIND_WORKER],
                'ident' => optional($log->company)->name,
                'name' => $worker->name ?? 'Terceirizado removido',
                'detail' => $worker->position ?? null,
                'telephone' => $worker->telephone ?? null,
            ];
        }

        if ($log->freelancer_id !== null) {
            $freelancer = $log->freelancer;

            return $base + [
                'key' => self::KIND_FREELANCER . ':' . $log->freelancer_id,
                'kind' => self::KIND_FREELANCER,
                'label' => self::KIND_LABELS[self::KIND_FREELANCER],
                'ident' => null,
                'name' => $freelancer->name ?? 'Freelancer removido',
                'detail' => null,
                'telephone' => $freelancer->telephone ?? null,
            ];
        }

        if ($log->one_off_access_id !== null) {
            $oneOff = $log->oneOffAccess;

            return $base + [
                'key' => self::KIND_ONE_OFF . ':' . $log->one_off_access_id,
                'kind' => self::KIND_ONE_OFF,
                'label' => self::KIND_LABELS[self::KIND_ONE_OFF],
                'ident' => null,
                'name' => $oneOff->name ?? 'Liberação removida',
                'detail' => $oneOff->reason ?? null,
                // A liberação pontual não guarda telefone.
                'telephone' => null,
            ];
        }

        return null;
    }

    /**
     * `parkings.entry_date` já foi gravado com a hora separada por hífen
     * ("2024-05-17 14-52-02"); AccessController::findAccessByTime trata igual.
     */
    private function parseEntryDate($entryDate): ?Carbon
    {
        if ($entryDate instanceof \DateTimeInterface) {
            return Carbon::instance($entryDate);
        }

        $parts = explode(' ', trim((string) $entryDate));

        if (count($parts) < 2) {
            return null;
        }

        try {
            return Carbon::parse($parts[0] . ' ' . str_replace('-', ':', $parts[1]));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Mesma normalização do pedido de Uber: maiúsculas, só letras e números. */
    private function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }
}
