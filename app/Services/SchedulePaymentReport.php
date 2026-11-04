<?php

namespace App\Services;

use App\Models\SchedulePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Os filtros da tela de Pagamentos (Reservas) e a extração em CSV.
 *
 * Fonte única dos filtros: a lista e o CSV saem da MESMA consulta, para a
 * planilha trazer exatamente o que a tela mostrou.
 *
 * Período (`date_from`/`date_to`) por `period_by`:
 *  - `paid` (padrão): a data do pagamento (`paid_at`) — o fechamento financeiro;
 *  - `use`: a data do horário reservado (`schedules.start_schedule`) — o que
 *    foi usado no período, pago antes ou depois.
 */
class SchedulePaymentReport
{
    public const PERIOD_PAID = 'paid';
    public const PERIOD_USE = 'use';

    public const PERIODS = [
        self::PERIOD_PAID => 'Data do pagamento',
        self::PERIOD_USE => 'Data do horário',
    ];

    public const HEADER = [
        'Pagamento', 'Pago em', 'Sócio', 'CPF', 'Título', 'Forma', 'Baixa manual', 'Status do pagamento',
        'Valor pago', 'Estornado', 'Horários no pagamento',
        'Grupo', 'Local', 'Data do horário', 'Início', 'Fim', 'Valor do horário', 'Status do horário',
    ];

    public function query(Request $request): Builder
    {
        $query = SchedulePayment::with(['status', 'schedules.place.group', 'schedules.member', 'schedules.status']);

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        // "manual" = toda baixa manual, qualquer que seja a forma.
        if ($request->input('payment_method') === 'manual') {
            $query->where('is_manual', true);
        } elseif ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('member')) {
            $term = $request->input('member');
            $query->whereHas('schedules.member', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('cpf', 'like', "%{$term}%");
            });
        }

        $from = $this->date($request->input('date_from'));
        $to = $this->date($request->input('date_to'));

        if ($this->periodBy($request) === self::PERIOD_USE) {
            if ($from || $to) {
                $query->whereHas('schedules', function ($q) use ($from, $to) {
                    if ($from) {
                        $q->where('start_schedule', '>=', $from->copy()->startOfDay());
                    }
                    if ($to) {
                        $q->where('start_schedule', '<=', $to->copy()->endOfDay());
                    }
                });
            }
        } else {
            if ($from) {
                $query->where('paid_at', '>=', $from->copy()->startOfDay());
            }
            if ($to) {
                $query->where('paid_at', '<=', $to->copy()->endOfDay());
            }
        }

        return $query->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function periodBy(Request $request): string
    {
        return $request->input('period_by') === self::PERIOD_USE ? self::PERIOD_USE : self::PERIOD_PAID;
    }

    /**
     * Uma linha por horário pago (o pagamento de N horários dá N linhas).
     *
     * Valor pago e estornado saem só na PRIMEIRA linha de cada pagamento: somar
     * a coluna na planilha dá o total certo. O valor de cada horário fica em
     * "Valor do horário".
     *
     * @return iterable<int, array<int, string>>
     */
    public function rows(Request $request): iterable
    {
        yield self::HEADER;

        foreach ($this->query($request)->lazy(500) as $payment) {
            $schedules = $payment->schedules->sortBy('start_schedule')->values();
            $pagamento = [
                (string) $payment->id,
                $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : '',
                (string) ($schedules->first()?->member?->name ?? ''),
                (string) ($schedules->first()?->member?->cpf ?? ''),
                (string) ($schedules->first()?->member?->title ?? ''),
                $payment->methodLabel(),
                $payment->is_manual ? 'Sim' : 'Não',
                (string) ($payment->status->portuguese ?? $payment->status->name ?? ''),
            ];

            $valores = [$this->money($payment->paid_amount), $this->money($payment->refunded_amount)];
            $quantos = (string) $schedules->count();

            if ($schedules->isEmpty()) {
                yield array_merge($pagamento, $valores, [$quantos], array_fill(0, 7, ''));

                continue;
            }

            foreach ($schedules as $i => $schedule) {
                yield array_merge($pagamento, $i === 0 ? $valores : ['', ''], [$quantos], [
                    (string) ($schedule->place?->group?->name ?? ''),
                    (string) ($schedule->place?->name ?? ''),
                    $schedule->start_schedule ? $schedule->start_schedule->format('d/m/Y') : '',
                    $schedule->start_schedule ? $schedule->start_schedule->format('H:i') : '',
                    $schedule->end_schedule ? $this->end($schedule->start_schedule, $schedule->end_schedule) : '',
                    $this->money($schedule->price),
                    (string) ($schedule->status->portuguese ?? $schedule->status->name ?? ''),
                ]);
            }
        }
    }

    /** O nome do arquivo, com o período quando houver. */
    public function filename(Request $request): string
    {
        $from = $this->date($request->input('date_from'));
        $to = $this->date($request->input('date_to'));
        $periodo = ($from ? $from->format('Y-m-d') : '') . ($from || $to ? '_a_' : '') . ($to ? $to->format('Y-m-d') : '');

        return 'pagamentos-reservas' . ($periodo !== '' ? '-' . trim($periodo, '_') : '') . '.csv';
    }

    /** Fim no mesmo dia mostra só a hora; em outro dia, a data também. */
    private function end(Carbon $start, Carbon $end): string
    {
        return $start->isSameDay($end) ? $end->format('H:i') : $end->format('d/m/Y H:i');
    }

    /** Vírgula decimal, sem milhar: o Excel em português lê como número. */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }

    private function date(mixed $value): ?Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
