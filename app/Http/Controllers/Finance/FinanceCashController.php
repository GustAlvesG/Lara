<?php

namespace App\Http\Controllers\Finance;

use App\Authorization\Permissions as P;
use App\Http\Controllers\Controller;
use App\Models\SchedulePayment;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketOrderLog;
use App\Models\Ticket\TicketPayment;
use App\Models\Tournament\TournamentPayment;
use App\Models\User;
use App\Services\Finance\CashReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financeiro › Caixa (gate `ver-caixa-financeiro`): o que cada pessoa
 * recebeu no balcão — Reservas, Torneios e Ingressos —, por dia e forma de
 * pagamento: tela, PDF para o fechamento e CSV. Sem `ingressos.caixa.todos`,
 * só o caixa de quem está logado (o filtro de pessoa é ignorado).
 */
class FinanceCashController extends Controller
{
    public function __construct(private CashReport $cash)
    {
    }

    public function index(Request $request): View
    {
        [$from, $to, $userId, $all] = $this->filters($request);

        return view('finance.cash.index', [
            'report' => $this->cash->report($from, $to, $userId),
            'userId' => $userId,
            'all' => $all,
            // Quem já registrou algo no balcão (o filtro do fechamento).
            'people' => $all ? $this->people() : collect(),
        ]);
    }

    public function pdf(Request $request): Response
    {
        [$from, $to, $userId, $all] = $this->filters($request);
        $report = $this->cash->report($from, $to, $userId);

        return Pdf::loadView('finance.cash.pdf', [
            'report' => $report,
            'person' => $userId ? User::find($userId) : null,
            'all' => $all,
            'by' => $request->user(),
        ])->setPaper('a4')->download('caixa-' . $from->format('Y-m-d') . ($from->isSameDay($to) ? '' : '-a-' . $to->format('Y-m-d')) . '.pdf');
    }

    public function csv(Request $request): StreamedResponse
    {
        [$from, $to, $userId] = $this->filters($request);
        $rows = $this->cash->csvRows($this->cash->report($from, $to, $userId));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM: o Excel abre com acento certo.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 'caixa-' . $from->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?int, 3: bool} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'de' => 'nullable|date_format:Y-m-d',
            'ate' => 'nullable|date_format:Y-m-d',
            'pessoa' => 'nullable|integer',
        ]);

        $from = isset($data['de']) ? Carbon::createFromFormat('Y-m-d', $data['de']) : Carbon::today();
        $to = isset($data['ate']) ? Carbon::createFromFormat('Y-m-d', $data['ate']) : $from->copy();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) >= CashReport::MAX_DAYS) {
            throw ValidationException::withMessages(['de' => 'Escolha um período de até ' . CashReport::MAX_DAYS . ' dias.']);
        }

        $all = $request->user()->can(P::INGRESSOS_CAIXA_TODOS);
        $userId = $all ? ($request->integer('pessoa') ?: null) : $request->user()->id;

        return [$from, $to, $userId, $all];
    }

    /** As pessoas que já registraram algo no balcão, para o filtro. */
    private function people()
    {
        $recorded = fn (string $model) => $model::where('is_manual', true)->whereNotNull('recorded_by')->distinct()->pluck('recorded_by');

        $ids = $recorded(SchedulePayment::class)
            ->merge($recorded(TournamentPayment::class))
            ->merge($recorded(TicketPayment::class))
            ->merge(TicketOrder::where('origin', TicketOrder::ORIGIN_COUNTER)->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->merge(TicketOrderLog::whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->unique()
            ->values();

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }
}
