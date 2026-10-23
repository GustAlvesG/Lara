<?php

namespace App\Services\Tournament;

use App\Models\SchedulePayment;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentPayment;
use App\Models\Tournament\TournamentRegistration;
use Illuminate\Support\Collection;

/**
 * Relatório financeiro dos torneios (F7).
 *
 * A conta é pela **inscrição**, porque um pagamento pode cobrir várias
 * categorias: recebido = valor das inscrições que foram pagas (ainda pagas
 * ou canceladas depois), separado em eRede e baixa manual; estornado = o
 * `refunded_amount` de cada inscrição; líquido = recebido − estornado;
 * a receber = inscrições aguardando pagamento.
 */
class TournamentFinanceService
{
    /**
     * Uma linha por categoria e a linha de total.
     *
     * @return array{categories: Collection<int, array>, total: array}
     */
    public function summary(Tournament $tournament): array
    {
        $tournament->loadMissing('categories');

        $registrations = TournamentRegistration::with('payment')
            ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->get()
            ->groupBy('tournament_category_id');

        $rows = $tournament->categories->map(fn ($category) => ['category' => $category] + $this->totals($registrations[$category->id] ?? collect()));

        return [
            'categories' => $rows,
            'total' => $this->totals($registrations->flatten(1)),
        ];
    }

    /** Pagamentos que cobrem inscrições do torneio, do mais novo ao mais antigo. */
    public function payments(Tournament $tournament): Collection
    {
        $categoryIds = $tournament->categories()->pluck('id');

        return TournamentPayment::with(['registrations' => fn ($q) => $q->whereIn('tournament_category_id', $categoryIds)->with('category'), 'recordedBy', 'refundedBy'])
            ->whereHas('registrations', fn ($q) => $q->whereIn('tournament_category_id', $categoryIds))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Totais de cada torneio, para a lista geral. */
    public function overview(Collection $tournaments): Collection
    {
        $registrations = TournamentRegistration::with(['payment', 'category'])
            ->whereHas('category', fn ($q) => $q->whereIn('tournament_id', $tournaments->pluck('id')))
            ->get()
            ->groupBy(fn ($r) => $r->category->tournament_id);

        return $tournaments->map(fn ($t) => ['tournament' => $t] + $this->totals($registrations[$t->id] ?? collect()));
    }

    /** CSV (`;`, UTF-8 com BOM, para o Excel): um pagamento por linha. */
    public function csv(Tournament $tournament): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Data do pagamento', 'Pessoa', 'CPF', 'Categorias', 'Forma', 'Baixa manual', 'TID', 'Valor pago', 'Estornado', 'Situação'], ';');

        foreach ($this->payments($tournament) as $payment) {
            $first = $payment->registrations->first();
            fputcsv($handle, [
                $payment->paid_at?->format('d/m/Y H:i'),
                $first?->name,
                $first?->cpf,
                $payment->registrations->map(fn ($r) => $r->category?->name)->filter()->unique()->implode(', '),
                self::methodLabel($payment->payment_method),
                $payment->is_manual ? 'sim' : 'não',
                $payment->payment_integration_id,
                number_format((float) $payment->paid_amount, 2, ',', ''),
                number_format((float) $payment->refunded_amount, 2, ',', ''),
                TournamentPayment::STATUS_LABELS[$payment->status] ?? $payment->status,
            ], ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /** "Dinheiro", "Pix direto ao clube"… para a baixa manual; a eRede fica como veio do site. */
    public static function methodLabel(?string $method): string
    {
        return SchedulePayment::MANUAL_METHODS[$method] ?? (string) $method;
    }

    /** @param  Collection<int, TournamentRegistration>  $registrations */
    private function totals(Collection $registrations): array
    {
        $charged = $registrations->filter(fn ($r) => $r->tournament_payment_id !== null);
        $manual = $charged->filter(fn ($r) => (bool) $r->payment?->is_manual);
        $pending = $registrations->where('status', TournamentRegistration::STATUS_PENDING_PAYMENT);

        $received = round((float) $charged->sum('amount'), 2);
        $refunded = round((float) $charged->sum('refunded_amount'), 2);

        return [
            'paid_count' => $registrations->where('status', TournamentRegistration::STATUS_PAID)->count(),
            'cancelled_after_payment' => $charged->where('status', TournamentRegistration::STATUS_CANCELLED)->count(),
            'received' => $received,
            'received_rede' => round($received - (float) $manual->sum('amount'), 2),
            'received_manual' => round((float) $manual->sum('amount'), 2),
            'refunded' => $refunded,
            'net' => round($received - $refunded, 2),
            'pending_count' => $pending->count(),
            'pending_amount' => round((float) $pending->sum('amount'), 2),
        ];
    }
}
