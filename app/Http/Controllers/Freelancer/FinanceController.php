<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayFreelancerServicesRequest;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Services\FreelancerService as FreelancerServiceManager;

/**
 * Aba Financeiro de Serviços / Contratos. Acesso restrito a quem está no setor
 * Contabilidade ou Gerência — Gate "manage-freelancer-payments".
 *
 * **O lote é a unidade de trabalho.** A diretoria aprova um bloco de contratos
 * e é esse bloco que o financeiro quita, então a tela abre pela lista de lotes
 * (`index`) e o pagamento acontece dentro de um deles (`batch`). Duas telas de
 * escape garantem que nenhum contrato pagável fique invisível: `orphans`, para
 * o que não tem lote, e `all`, a lista plana de sempre.
 *
 * O botão de pagar tem dois comportamentos, decididos por `sicoob.enabled`:
 *
 *   desligado → marcação manual, como sempre foi. O dinheiro sai por fora.
 *   ligado    → enfileira um Pix de verdade. O contrato NÃO fica pago no
 *               clique: fica "em processamento", e só recebe a baixa quando o
 *               banco confirmar (ver FreelancerService::markAsPaidFromPix).
 */
class FinanceController extends Controller
{
    /**
     * Relações que a tabela do financeiro lê. `latestPixPayment` é obrigatória:
     * sem ela `hasPixInProgress()` responde `false` por falta da relação e a
     * tela volta a oferecer o botão de pagar para um Pix em andamento.
     */
    private const TABLE_RELATIONS = [
        'freelancer:id,name,cpf,pix_key',
        'functionFreelancer',
        'paidBy',
        'latestPixPayment',
    ];

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    /** Lista de lotes aprovados pela diretoria, com o que falta pagar em cada um. */
    public function index()
    {
        $batches = FreelancerServiceBatch::approvedForFinance()
            ->withCount([
                'services as payable_count' => fn($q) => $q->awaitingFinance(),
                'services as paid_count' => fn($q) => $q->awaitingFinance()->where('paid', true),
            ])
            ->withSum(['services as payable_total' => fn($q) => $q->awaitingFinance()], 'price')
            ->withSum(['services as paid_total' => fn($q) => $q->awaitingFinance()->where('paid', true)], 'price')
            ->with(['createdBy', 'reviewedBy', 'directorDecidedBy'])
            ->orderByDesc('director_decided_at')
            ->orderByDesc('id')
            ->get();

        // Separado em PHP e não por `orderBy` de alias agregado: é mais legível
        // e não depende do dialeto do banco.
        [$quitados, $aPagar] = $batches->partition->isFullyPaid();

        return view('freelancer.services.finance', [
            'aPagar' => $aPagar->values(),
            'quitados' => $quitados->values(),
            'pendingTotal' => $batches->sum(fn($b) => (float) $b->payable_total - (float) $b->paid_total),
            'paidTotal' => $batches->sum(fn($b) => (float) $b->paid_total),
            'orphanCount' => $this->orphanQuery()->count(),
            'pixEnabled' => (bool) config('sicoob.enabled'),
            'pixAmbiente' => config('sicoob.environment'),
        ]);
    }

    /** Contratos de um lote — onde a baixa acontece. */
    public function batch(FreelancerServiceBatch $batch)
    {
        abort_unless($batch->isDirectorApproved(), 404);

        $services = $this->orderedForFinance($batch->payableServices()->with(self::TABLE_RELATIONS))->get();

        return view('freelancer.services.finance-batch', [
            'batch' => $batch->load(['createdBy', 'reviewedBy', 'directorDecidedBy']),
            'services' => $services,
            'pendingTotal' => $services->where('paid', false)->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
            'pixEnabled' => (bool) config('sicoob.enabled'),
            'pixAmbiente' => config('sicoob.environment'),
        ]);
    }

    /**
     * Relação do lote para impressão: uma linha por contrato, com o cadastro do
     * freelancer e a trilha completa das três aprovações. Documento de
     * conferência do contábil — abre em aba nova e chama a impressão sozinho.
     */
    public function print(FreelancerServiceBatch $batch)
    {
        abort_unless($batch->isDirectorApproved(), 404);

        $services = $this->orderedForFinance(
            $batch->payableServices()->with([
                'freelancer',
                'functionFreelancer',
                'coordinatorSignedBy',
                'managerApprovedBy',
                'paidBy',
            ])
        )->get();

        return view('freelancer.services.finance-print', [
            'batch' => $batch->load(['createdBy', 'reviewedBy', 'directorDecidedBy']),
            'services' => $services,
            'total' => $services->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
            'pendingTotal' => $services->where('paid', false)->sum('price'),
        ]);
    }

    /**
     * Contratos pagáveis fora de qualquer lote aprovado. Não deveria existir
     * nenhum — `availableForBatch` impede um contrato aprovado de ser
     * reloteado —, mas a tela existe para que dinheiro nunca suma da vista se
     * um dado antigo ou uma exclusão de lote (`nullOnDelete`) criar um órfão.
     */
    public function orphans()
    {
        $services = $this->orderedForFinance($this->orphanQuery()->with(self::TABLE_RELATIONS))->get();

        return view('freelancer.services.finance-flat', [
            'titulo' => 'Contratos sem lote',
            'subtitulo' => 'Aprovados e pagáveis, mas fora de qualquer lote aprovado pela diretoria. Confira antes de pagar.',
            'services' => $services,
            'emptyText' => 'Nenhum contrato solto — todos os pagáveis estão em lotes.',
            'pendingTotal' => $services->where('paid', false)->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
            'pixEnabled' => (bool) config('sicoob.enabled'),
            'pixAmbiente' => config('sicoob.environment'),
        ]);
    }

    /** A lista plana de sempre: busca transversal e seleção entre lotes. */
    public function all()
    {
        $services = $this->orderedForFinance(FreelancerService::awaitingFinance()->with(self::TABLE_RELATIONS))->get();

        return view('freelancer.services.finance-flat', [
            'titulo' => 'Todos os contratos',
            'subtitulo' => 'Todos os contratos aprovados pela diretoria, de todos os lotes, numa lista só.',
            'services' => $services,
            'emptyText' => 'Nenhum contrato aprovado aguardando pagamento.',
            'pendingTotal' => $services->where('paid', false)->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
            'pixEnabled' => (bool) config('sicoob.enabled'),
            'pixAmbiente' => config('sicoob.environment'),
        ]);
    }

    /**
     * Baixa individual (botão da linha) e em massa (checkboxes) — mesma rota,
     * porque a tabela não comporta um formulário por linha.
     */
    public function pay(PayFreelancerServicesRequest $request)
    {
        $ids = $request->serviceIds();
        $redirect = redirect()->to($request->returnUrl());

        if (!$ids) {
            return $redirect->with('error', 'Selecione ao menos um contrato para dar baixa.');
        }

        return config('sicoob.enabled')
            ? $this->payWithPix($ids, $request, $redirect)
            : $this->payManually($ids, $request, $redirect);
    }

    /**
     * Fluxo com Pix automático: o clique só enfileira. Nenhuma mensagem aqui
     * afirma que o pagamento foi feito — a tela não pode prometer o que ainda
     * depende do banco.
     */
    private function payWithPix(array $ids, PayFreelancerServicesRequest $request, $redirect)
    {
        $resultado = $this->freelancerService->requestPixForMany($ids, $request->user());

        if ($resultado['queued'] === 0) {
            return $redirect
                ->with('error', 'Nenhum Pix foi enviado.')
                ->with('warning', $resultado['problems']
                    ? implode(' · ', $resultado['problems'])
                    : 'Os contratos selecionados já foram pagos ou não estão aptos.');
        }

        $redirect->with('success', $resultado['queued'] === 1
            ? 'Pix enviado para processamento. A baixa é registrada quando o banco confirmar.'
            : "{$resultado['queued']} Pix enviados para processamento. A baixa de cada contrato é registrada quando o banco confirmar.");

        if ($resultado['problems']) {
            $redirect->with('warning', implode(' · ', $resultado['problems']));
        } elseif ($resultado['skipped'] > 0) {
            $redirect->with('warning', $resultado['skipped']
                . ' contrato(s) selecionado(s) foram ignorados por já estarem pagos ou não estarem aptos.');
        }

        return $redirect;
    }

    /** Comportamento original: baixa manual, sem tocar no banco. */
    private function payManually(array $ids, PayFreelancerServicesRequest $request, $redirect)
    {
        $paid = $this->freelancerService->markManyAsPaid($ids, $request->user());

        if ($paid === 0) {
            return $redirect->with('error', 'Nenhum contrato apto para baixa — verifique se já não foram pagos.');
        }

        $redirect->with('success', $paid === 1
            ? 'Baixa de pagamento registrada.'
            : "Baixa de pagamento registrada em {$paid} contratos.");

        // Um id selecionado que não recebeu baixa quase sempre significa que
        // outra pessoa já pagou aquele contrato com a tela aberta.
        if ($paid < count($ids)) {
            $redirect->with('warning', (count($ids) - $paid) . ' contrato(s) selecionado(s) foram ignorados por já estarem pagos ou não estarem aptos.');
        }

        return $redirect;
    }

    /** Pendentes primeiro; dentro de cada grupo, os mais recentes no topo. */
    private function orderedForFinance($query)
    {
        return $query->orderBy('paid')->orderByDesc('start_date');
    }

    /**
     * Pagável e sem lote aprovado por trás — inclui o caso teórico de um lote
     * que não está `director_approved`, para que nada escape das duas telas.
     */
    private function orphanQuery()
    {
        return FreelancerService::awaitingFinance()
            ->where(fn($q) => $q->whereNull('batch_id')
                ->orWhereDoesntHave('batch', fn($b) => $b->where('status', FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED)));
    }
}
