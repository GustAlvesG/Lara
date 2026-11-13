<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\FinanceLedger;
use App\Services\Finance\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financeiro › Recebimentos (`financeiro.recebimentos`): todo pagamento de
 * Reservas, Torneios e Ingressos numa lista só, com filtros e CSV. O detalhe
 * e o estorno continuam na tela de cada origem — a lista leva até ela.
 */
class FinanceReceiptController extends Controller
{
    use FinanceFilters;

    public function index(Request $request, FinanceLedger $ledger): View
    {
        $filters = $this->filters($request);
        $receipts = $ledger->query($filters)->paginate(30)->withQueryString();
        $recorders = User::whereIn('id', collect($receipts->items())->pluck('recorded_by')->filter()->unique())->pluck('name', 'id');

        return view('finance.receipts', [
            'filters' => $filters,
            'query' => $this->query($filters),
            'receipts' => $receipts,
            'recorders' => $recorders,
            'summary' => $ledger->summary($filters),
            'methods' => $this->methodOptions(),
        ]);
    }

    public function csv(Request $request, FinanceLedger $ledger): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $ledger->query($filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM: o Excel abre com acento certo.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Origem', 'Código', 'Pago em', 'Pagador', 'CPF', 'Referência', 'Forma', 'Baixa manual', 'Registrado por', 'TID', 'Situação', 'Valor pago', 'Estornado', 'Líquido'], ';');

            $names = [];
            foreach ($rows->lazy(500) as $row) {
                if ($row->recorded_by && ! isset($names[$row->recorded_by])) {
                    $names[$row->recorded_by] = (string) User::whereKey($row->recorded_by)->value('name');
                }
                fputcsv($out, [
                    FinanceLedger::ORIGINS[$row->origin] ?? $row->origin,
                    (string) $row->id,
                    $row->paid_at ? Carbon::parse($row->paid_at)->format('d/m/Y H:i') : '',
                    (string) $row->payer_name,
                    (string) $row->payer_cpf,
                    (string) $row->reference,
                    PaymentMethods::label($row->payment_method),
                    $row->is_manual ? 'Sim' : 'Não',
                    $row->recorded_by ? ($names[$row->recorded_by] ?? '') : '',
                    (string) $row->tid,
                    FinanceLedger::STATUSES[FinanceLedger::status($row)],
                    self::money($row->paid_amount),
                    self::money($row->refunded_amount),
                    self::money((float) $row->paid_amount - (float) $row->refunded_amount),
                ], ';');
            }
            fclose($out);
        }, 'recebimentos-' . $filters['from']->format('Y-m-d') . '-a-' . $filters['to']->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Filtro de forma: os grupos (eRede, baixa manual) e cada forma. */
    private function methodOptions(): array
    {
        $online = array_unique(PaymentMethods::ONLINE);

        return [FinanceLedger::METHOD_ONLINE => 'Site / eRede (todas)', FinanceLedger::METHOD_MANUAL => 'Baixa manual (todas)']
            + ['credit' => $online['credit'], 'debit' => $online['debit'], 'pix' => $online['pix']]
            + PaymentMethods::MANUAL;
    }

    /** Vírgula decimal, sem milhar: o Excel em português lê como número. */
    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
