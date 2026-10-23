<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament\Tournament;
use App\Services\Tournament\TournamentFinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Relatório financeiro (permissão `torneios.financeiro`): a lista de
 * torneios com os totais, o relatório de um torneio por categoria com os
 * pagamentos, e o CSV dos pagamentos. Contas em TournamentFinanceService.
 */
class TournamentFinanceController extends Controller
{
    public function __construct(private TournamentFinanceService $finance)
    {
    }

    public function index(Request $request): View
    {
        $year = $request->integer('ano') ?: null;

        $tournaments = Tournament::with('placeGroup')
            ->where('status', '!=', Tournament::STATUS_DRAFT)
            ->when($year, fn ($q) => $q->whereYear('starts_on', $year))
            ->orderByDesc('starts_on')
            ->get();

        $rows = $this->finance->overview($tournaments);

        return view('tournaments.finance.index', [
            'rows' => $rows,
            'year' => $year,
            'years' => Tournament::where('status', '!=', Tournament::STATUS_DRAFT)->pluck('starts_on')->map(fn ($d) => $d->year)->unique()->sortDesc()->values(),
            'totals' => [
                'received' => round($rows->sum('received'), 2),
                'refunded' => round($rows->sum('refunded'), 2),
                'net' => round($rows->sum('net'), 2),
                'pending_amount' => round($rows->sum('pending_amount'), 2),
            ],
        ]);
    }

    public function show(Tournament $tournament): View
    {
        return view('tournaments.finance.show', [
            'tournament' => $tournament,
            'summary' => $this->finance->summary($tournament),
            'payments' => $this->finance->payments($tournament),
        ]);
    }

    public function csv(Tournament $tournament): Response
    {
        $name = 'financeiro-' . Str::slug($tournament->title) . '-' . now()->format('Ymd') . '.csv';

        return response($this->finance->csv($tournament), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }
}
