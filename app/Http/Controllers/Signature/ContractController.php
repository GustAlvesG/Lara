<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\ClaudeException;
use App\Exceptions\ContractException;
use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureContractAttachment;
use App\Models\SignatureContractComment;
use App\Models\SignatureContractModel;
use App\Models\SignatureContractParty;
use App\Models\SignatureContractVersion;
use App\Services\Signature\Contracts\ContractAiReviewer;
use App\Services\Signature\Contracts\ContractClub;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractDiff;
use App\Services\Signature\Contracts\ContractDocxExporter;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Contracts\ContractVariables;
use App\Services\Signature\Directors\DirectorAreaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Assinaturas → Contratos: redigir, validar (Gerência) e gerar o documento
 * de assinatura. As regras de estado são do ContractService; quem pode, da
 * SignatureContractPolicy.
 */
class ContractController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ContractService $contracts,
        private ContractRenderer $renderer,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', SignatureContract::class);

        $user = $request->user();
        $gerencia = $user->isManagementMember();
        $aba = $gerencia && $request->query('aba') === 'validar' ? 'validar' : 'todos';
        $status = $request->query('status');
        $busca = trim((string) $request->query('q', ''));

        $contratos = SignatureContract::query()
            ->visibleTo($user->sectorIds(), $gerencia)
            ->when($aba === 'validar', fn($q) => $q->where('status', SignatureContract::STATUS_IN_REVIEW))
            ->when($aba === 'todos' && isset(SignatureContract::STATUS_LABELS[$status]), fn($q) => $q->where('status', $status))
            ->search($busca)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('signature.contracts.index', [
            'contracts' => $contratos,
            'aba' => $aba,
            'status' => $status,
            'busca' => $busca,
            'gerencia' => $gerencia,
            'aguardando' => $gerencia ? SignatureContract::where('status', SignatureContract::STATUS_IN_REVIEW)->count() : 0,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SignatureContract::class);

        $setores = $this->sectorsOf($request);

        return view('signature.contracts.create', [
            'sectors' => $setores,
            'models' => SignatureContractModel::whereIn('sector_id', $setores->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SignatureContract::class);

        $setores = $this->sectorsOf($request);

        $dados = $request->validate([
            'sector_id' => ['required', 'integer', 'in:' . $setores->pluck('id')->join(',')],
            'title' => ['nullable', 'string', 'max:200'],
            'model_id' => ['nullable', 'integer'],
        ], [
            'sector_id.in' => 'Escolha um dos seus setores.',
            'sector_id.required' => 'Você não está em nenhum setor: peça para ser incluído antes de redigir contratos.',
        ]);

        $modelo = !empty($dados['model_id'])
            ? SignatureContractModel::where('id', $dados['model_id'])->where('sector_id', $dados['sector_id'])->first()
            : null;

        $contrato = $this->contracts->create(
            (int) $dados['sector_id'],
            $setores->firstWhere('id', (int) $dados['sector_id'])?->name,
            $dados['title'] ?? null,
            $modelo,
            $request->user()->id,
            $request->user()->name,
        );

        return redirect()->route('signature-contracts.edit', $contrato)
            ->with('success', 'Contrato criado. Preencha as partes, as variáveis e as cláusulas.');
    }

    public function show(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('view', $signatureContract);

        $contrato = $signatureContract->load(['versions', 'comments', 'events', 'attachments', 'document', 'parent', 'addenda']);
        $conteudo = $contrato->content ?? [];

        // A comparação é com a última versão DEVOLVIDA: é o que a Gerência viu por último.
        $devolvida = $contrato->versions->firstWhere('decision', SignatureContractVersion::DECISION_RETURNED);
        $diff = $devolvida && in_array($contrato->status, [SignatureContract::STATUS_IN_REVIEW, SignatureContract::STATUS_RETURNED, SignatureContract::STATUS_DRAFT], true)
            ? app(ContractDiff::class)->compare($devolvida->content ?? [], $conteudo)
            : null;

        return view('signature.contracts.show', $this->aiReviewData($request, $contrato) + [
            'contract' => $contrato,
            'preview' => $this->renderer->html($conteudo, $contrato->number),
            'problems' => $this->renderer->problems($conteudo),
            'outline' => $this->renderer->outline($conteudo),
            'diff' => $diff,
            'diffVersion' => $devolvida,
        ]);
    }

    public function edit(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('update', $signatureContract);

        $conteudo = $signatureContract->content ?? ContractContent::blank();

        return view('signature.contracts.edit', $this->aiReviewData($request, $signatureContract) + [
            'contract' => $signatureContract->load(['comments']),
            'content' => $conteudo,
            'preview' => $this->renderer->html($conteudo, $signatureContract->number),
            'problems' => $this->renderer->problems($conteudo),
            'outline' => $this->renderer->outline($conteudo),
            'tokens' => $this->renderer->availableTokens($conteudo),
            'establishments' => app(ContractClub::class)->establishmentOptions(),
            'president' => app(ContractClub::class)->president(),
            'variableTypes' => ContractVariables::LABELS,
            'directors' => app(DirectorAreaService::class)->options(),
            'openComments' => $signatureContract->comments->whereNull('resolved_at'),
        ]);
    }

    public function update(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('update', $signatureContract);

        if ($request->input('next') === 'ai-review') {
            $this->authorize('aiReview', $signatureContract);
        }

        try {
            $this->contracts->save($signatureContract, $this->contentInput($request), $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($request->input('next') === 'submit') {
            return $this->submit($request, $signatureContract->fresh());
        }

        // "Salvar e analisar", no editor: a análise é sempre do texto salvo.
        if ($request->input('next') === 'ai-review') {
            return $this->runAiReview($request, $signatureContract->fresh(), 'signature-contracts.edit');
        }

        return redirect()->route('signature-contracts.edit', $signatureContract)->with('success', 'Contrato salvo.');
    }

    /**
     * A prévia do que está no formulário, sem gravar: texto numerado e lista
     * de problemas. Mesmo renderizador do documento final.
     */
    public function preview(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('update', $signatureContract);

        $conteudo = ContractContent::normalize($this->contentInput($request));

        return response()->json([
            'html' => $this->renderer->html($conteudo, $signatureContract->number),
            'problems' => $this->renderer->problems($conteudo),
            'outline' => collect($this->renderer->outline($conteudo))
                ->map(fn(array $o, string $uid) => ['uid' => $uid] + $o)
                ->values(),
            'tokens' => $this->renderer->availableTokens($conteudo),
        ]);
    }

    public function submit(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('submit', $signatureContract);

        $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->contracts->submit($signatureContract, $request->input('note'), $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return $this->refused($e, $signatureContract);
        }

        return redirect()->route('signature-contracts.show', $signatureContract)
            ->with('success', 'Contrato enviado à Gerência para validação.');
    }

    /** Aprovar ou devolver (Gerência). */
    public function decide(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('decide', $signatureContract);

        $dados = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'note' => ['nullable', 'string', 'max:3000'],
            'comments' => ['nullable', 'array', 'max:' . ContractContent::MAX_CLAUSES],
            'comments.*' => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user();

        try {
            if ($dados['decision'] === 'return') {
                $this->contracts->returnToAuthor($signatureContract, $dados['note'] ?? null, $dados['comments'] ?? [], $user->id, $user->name);

                return redirect()->route('signature-contracts.show', $signatureContract)
                    ->with('success', 'Contrato devolvido com as observações. Quem redigiu foi avisado por e-mail.');
            }

            $resultado = $this->contracts->approve($signatureContract, $dados['note'] ?? null, $user->id, $user->name);
        } catch (ContractException $e) {
            return $this->refused($e, $signatureContract);
        }

        $redirect = redirect()->route('signature-contracts.show', $signatureContract)
            ->with('success', 'Contrato aprovado: ' . $resultado['contract']->number
                . ($resultado['document'] ? '. O documento de assinatura foi gerado em rascunho.' : '.'));

        return $resultado['warnings'] !== [] ? $redirect->with('warning', implode(' ', $resultado['warnings'])) : $redirect;
    }

    /** Revisão automática pelo Claude: resumo das inconsistências e sugestões (quem redige e a Gerência). */
    public function aiReview(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('aiReview', $signatureContract);

        return $this->runAiReview($request, $signatureContract, 'signature-contracts.show');
    }

    /** @param  string  $rota  a tela para onde voltar (a do contrato ou o editor) */
    private function runAiReview(Request $request, SignatureContract $signatureContract, string $rota)
    {
        $reviewer = app(ContractAiReviewer::class);

        if (!$reviewer->isAvailable()) {
            return redirect()->route($rota, $signatureContract)->with('error', 'A revisão automática não está configurada (falta a chave da API do Claude).');
        }

        // Os trechos sensíveis que a pessoa marcou para enviar; o resto deles fica de fora.
        $dados = $request->validate([
            'include' => ['nullable', 'array', 'max:200'],
            'include.*' => ['string', 'max:80'],
        ]);

        // O "Salvar e analisar" passa pela rota de salvar, sem o throttle da rota da revisão.
        $limite = 'contract-ai-review:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($limite, 6)) {
            return redirect()->route($rota, $signatureContract)->with('warning', 'Muitas análises seguidas. Espere um minuto e peça de novo.');
        }

        RateLimiter::hit($limite, 60);

        try {
            $resultado = $reviewer->review($signatureContract, $request->user()->id, $request->user()->name, $dados['include'] ?? []);
        } catch (ClaudeException $e) {
            return redirect()->route($rota, $signatureContract)->with('error', $e->getMessage());
        }

        return redirect()->to(route($rota, $signatureContract) . '#revisao-ia')
            ->with('success', $resultado['reused']
                ? 'O texto não mudou desde a última revisão automática: ela continua valendo.'
                : 'Revisão automática concluída. Confira os apontamentos antes de usar.');
    }

    public function reopen(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('manage', $signatureContract);

        try {
            $this->contracts->reopen($signatureContract, $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-contracts.edit', $signatureContract)
            ->with('success', 'Contrato reaberto. Depois de alterar, envie de novo à Gerência.');
    }

    public function cancel(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('manage', $signatureContract);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->contracts->cancel($signatureContract, $request->input('reason'), $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-contracts.show', $signatureContract)->with('success', 'Contrato cancelado.');
    }

    public function addendum(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('addendum', $signatureContract);

        try {
            $aditivo = $this->contracts->createAddendum($signatureContract, $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-contracts.edit', $aditivo)
            ->with('success', 'Aditivo criado com as partes, variáveis e signatários do contrato. Escreva as cláusulas do aditivo.');
    }

    public function generateDocument(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('manage', $signatureContract);

        try {
            $resultado = $this->contracts->generateDocument($signatureContract, $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        $redirect = redirect()->route('signature-contracts.show', $signatureContract);

        if (!$resultado['document']) {
            return $redirect->with('warning', implode(' ', $resultado['warnings']));
        }

        $redirect->with('success', 'Documento de assinatura gerado em rascunho.');

        return $resultado['warnings'] !== [] ? $redirect->with('warning', implode(' ', $resultado['warnings'])) : $redirect;
    }

    /** O contrato em Word, para mandar ao fornecedor revisar. */
    public function docx(SignatureContract $signatureContract, ContractDocxExporter $exporter)
    {
        $this->authorize('view', $signatureContract);

        $nome = Str::slug(($signatureContract->number ?: 'contrato-' . $signatureContract->id) . ' ' . ($signatureContract->counterpartyName() ?? $signatureContract->title))
            . '.docx';

        return response($exporter->export($signatureContract), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function saveModel(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('view', $signatureContract);
        $this->authorize('create', SignatureContract::class);

        $dados = $request->validate(['name' => ['required', 'string', 'max:150']]);

        $this->contracts->saveAsModel($signatureContract, $dados['name'], $request->user()->id, $request->user()->name);

        return back()->with('success', 'Modelo "' . $dados['name'] . '" salvo para o setor ' . ($signatureContract->sector_name ?? '') . '.');
    }

    public function resolveComment(Request $request, SignatureContract $signatureContract, SignatureContractComment $signatureContractComment)
    {
        $this->authorize('view', $signatureContract);

        abort_unless($signatureContractComment->signature_contract_id === $signatureContract->id, 404);

        $this->contracts->resolveComment(
            $signatureContractComment,
            $request->boolean('resolved', true),
            $request->user()->id,
            $request->user()->name,
        );

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function storeAttachment(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('manage', $signatureContract);

        $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:' . (int) config('signature.contracts.attachments_max_kb', 10240)],
        ], ['label.required' => 'Diga o que é o anexo (ex.: Anexo I — Proposta nº 3653498).']);

        try {
            $this->contracts->addAttachment($signatureContract, $request->file('file'), $request->input('label'), $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Anexo incluído. Ele segue junto para a assinatura.');
    }

    public function showAttachment(SignatureContract $signatureContract, SignatureContractAttachment $signatureContractAttachment)
    {
        $this->authorize('view', $signatureContract);

        abort_unless($signatureContractAttachment->signature_contract_id === $signatureContract->id, 404);

        $disk = Storage::disk(config('signature.disk'));

        abort_unless($disk->exists($signatureContractAttachment->path), 404);

        return $disk->response($signatureContractAttachment->path, $signatureContractAttachment->original_name, [
            'Content-Type' => $signatureContractAttachment->mime,
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function destroyAttachment(Request $request, SignatureContract $signatureContract, SignatureContractAttachment $signatureContractAttachment)
    {
        $this->authorize('manage', $signatureContract);

        abort_unless($signatureContractAttachment->signature_contract_id === $signatureContract->id, 404);

        try {
            $this->contracts->removeAttachment($signatureContractAttachment, $request->user()->id, $request->user()->name);
        } catch (ContractException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Anexo removido.');
    }

    /** Contratadas e fiadores já usados, para preencher a parte (busca por nome ou documento). */
    public function parties(Request $request)
    {
        $this->authorize('create', SignatureContract::class);

        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $digitos = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $termo));

        return response()->json(
            SignatureContractParty::query()
                ->where(fn($q) => $q->where('name', 'like', '%' . $termo . '%')
                    ->when(strlen($digitos) >= 3, fn($w) => $w->orWhere('document', 'like', $digitos . '%')))
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->map(fn(SignatureContractParty $p) => ['kind' => $p->kind, 'name' => $p->name, 'document' => $p->document] + ($p->data ?? [])),
        );
    }

    /**
     * O cartão da revisão automática, para a tela do contrato e o editor. Quem
     * vê o contrato vê a revisão; só some sem a chave configurada.
     *
     * @return array<string, mixed>
     */
    private function aiReviewData(Request $request, SignatureContract $contrato): array
    {
        $revisor = app(ContractAiReviewer::class);
        $disponivel = $revisor->isAvailable();
        $revisao = $disponivel ? $contrato->aiReviews()->first() : null;

        return [
            'aiEnabled' => $disponivel,
            'aiReview' => $revisao,
            'aiReviewCurrent' => $revisao ? $revisor->isCurrent($revisao, $contrato) : false,
            // Os trechos com valores, CPF, documentos ou endereço: ficam de fora, a menos que a pessoa marque.
            'aiSensitive' => $disponivel && $request->user()->can('aiReview', $contrato) ? $revisor->sensitiveSections($contrato) : [],
        ];
    }

    /** O que o formulário do editor manda, como entrada do ContractContent. */
    private function contentInput(Request $request): array
    {
        return $request->only(['title', 'establishment', 'contractor_term', 'parties', 'variables', 'considerations', 'clauses', 'signers']);
    }

    private function refused(ContractException $e, SignatureContract $contract)
    {
        return redirect()->route($contract->isEditableBySector() && request()->user()->can('update', $contract)
            ? 'signature-contracts.edit'
            : 'signature-contracts.show', $contract)
            ->with('warning', $e->getMessage())
            ->with('contractProblems', $e->problems);
    }

    /** Os setores do usuário, onde ele pode criar contrato. */
    private function sectorsOf(Request $request)
    {
        $ids = $request->user()->sectorIds();

        return $ids === [] ? collect() : Sector::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }
}
