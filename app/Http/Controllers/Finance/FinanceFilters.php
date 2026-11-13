<?php

namespace App\Http\Controllers\Finance;

use App\Services\Finance\FinanceLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Os filtros das telas Visão geral e Recebimentos (mesma consulta, mesmos
 * nomes na URL): `de`, `ate`, `origem`, `forma`, `situacao`, `busca`.
 * Sem período, o mês corrente.
 */
trait FinanceFilters
{
    /** @return array{from: Carbon, to: Carbon, origin: ?string, method: ?string, status: ?string, search: ?string} */
    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'de' => 'nullable|date_format:Y-m-d',
            'ate' => 'nullable|date_format:Y-m-d',
            'origem' => 'nullable|string|max:20',
            'forma' => 'nullable|string|max:40',
            'situacao' => 'nullable|string|max:20',
            'busca' => 'nullable|string|max:100',
        ]);

        $from = isset($data['de']) ? Carbon::createFromFormat('Y-m-d', $data['de']) : Carbon::today()->startOfMonth();
        $to = isset($data['ate']) ? Carbon::createFromFormat('Y-m-d', $data['ate']) : Carbon::today();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'origin' => isset(FinanceLedger::ORIGINS[$data['origem'] ?? '']) ? $data['origem'] : null,
            'method' => $data['forma'] ?? null,
            'status' => isset(FinanceLedger::STATUSES[$data['situacao'] ?? '']) ? $data['situacao'] : null,
            'search' => $data['busca'] ?? null,
        ];
    }

    /** Os filtros de volta para a URL (links de exportar e de origem). */
    protected function query(array $filters): array
    {
        return array_filter([
            'de' => $filters['from']->format('Y-m-d'),
            'ate' => $filters['to']->format('Y-m-d'),
            'origem' => $filters['origin'] ?? null,
            'forma' => $filters['method'] ?? null,
            'situacao' => $filters['status'] ?? null,
            'busca' => $filters['search'] ?? null,
        ]);
    }
}
