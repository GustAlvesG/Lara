<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureContractClause;
use App\Models\SignatureContractModel;
use App\Services\Signature\Contracts\ContractClub;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Contracts\ContractVariables;
use App\Services\Signature\Directors\DirectorAreaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Biblioteca de cláusulas e modelos de contrato, POR SETOR (decisão do
 * usuário): cada setor vê e mantém os seus. A Gerência enxerga as de todos,
 * como enxerga os contratos. Desde 04/11/2026, cadastrar, editar e excluir
 * pede `contratos.biblioteca` (SignatureContractPolicy::manageLibrary);
 * consultar e usar no editor seguem de quem redige. Desde 06/11/2026 o
 * modelo também se edita aqui (editModel/updateModel), no editor do contrato.
 */
class ContractLibraryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewLibrary', SignatureContract::class);

        $setores = $this->visibleSectorIds($request);
        $busca = trim((string) $request->query('q', ''));
        $setor = (int) $request->query('setor', 0);

        $clausulas = SignatureContractClause::query()
            ->when($setores !== null, fn($q) => $q->whereIn('sector_id', $setores ?: [0]))
            ->when($setor > 0, fn($q) => $q->where('sector_id', $setor))
            ->when($busca !== '', fn($q) => $q->where(fn($w) => $w
                ->where('title', 'like', '%' . $busca . '%')
                ->orWhere('category', 'like', '%' . $busca . '%')
                ->orWhere('items', 'like', '%' . $busca . '%')))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        $modelos = SignatureContractModel::query()
            ->when($setores !== null, fn($q) => $q->whereIn('sector_id', $setores ?: [0]))
            ->orderBy('name')
            ->get();

        return view('signature.contracts.library', [
            'clauses' => $clausulas,
            'models' => $modelos,
            'busca' => $busca,
            'setor' => $setor,
            'sectors' => $this->writableSectors($request),
            'editing' => $request->filled('editar')
                ? $this->findVisible($request, (int) $request->query('editar'))
                : null,
        ]);
    }

    /**
     * As cláusulas que o editor do contrato oferece em "Da biblioteca": as do
     * setor do contrato.
     */
    public function json(Request $request, SignatureContract $signatureContract)
    {
        $this->authorize('update', $signatureContract);

        return response()->json($this->clausesOf($signatureContract->sector_id));
    }

    private function clausesOf(?int $sectorId)
    {
        return SignatureContractClause::where('sector_id', $sectorId)
            ->orderBy('category')
            ->orderBy('title')
            ->get(['id', 'title', 'category', 'items'])
            ->map(fn(SignatureContractClause $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'category' => $c->category,
                // uid novo a cada inserção: a mesma cláusula duas vezes não compartilha uid.
                'items' => ContractContent::freshUids([['uid' => null, 'title' => $c->title, 'items' => $c->items ?? []]])[0]['items'],
            ]);
    }

    private function modelInput(Request $request): array
    {
        return $request->only(['title', 'establishment', 'contractor_term', 'parties', 'variables', 'considerations', 'clauses', 'signers', 'validity']);
    }

    /**
     * Salva cláusula na biblioteca: pela tela da biblioteca ou pelo botão
     * "Salvar na biblioteca" do editor (JSON).
     */
    public function store(Request $request)
    {
        $this->authorize('manageLibrary', SignatureContract::class);

        $dados = $this->validated($request);

        $clausula = SignatureContractClause::create([
            'sector_id' => $dados['sector_id'],
            'sector_name' => $this->writableSectors($request)->firstWhere('id', (int) $dados['sector_id'])?->name,
            'title' => $dados['title'],
            'category' => $dados['category'] ?? null,
            'items' => ContractContent::items($dados['items']),
            'created_by' => $request->user()->id,
            'created_by_name' => $request->user()->name,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $clausula->id]);
        }

        return redirect()->route('contract-library.index')->with('success', 'Cláusula salva na biblioteca.');
    }

    public function update(Request $request, SignatureContractClause $signatureContractClause)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractClause->sector_id);

        $dados = $this->validated($request);

        $signatureContractClause->update([
            'sector_id' => $dados['sector_id'],
            'sector_name' => $this->writableSectors($request)->firstWhere('id', (int) $dados['sector_id'])?->name,
            'title' => $dados['title'],
            'category' => $dados['category'] ?? null,
            'items' => ContractContent::items($dados['items']),
            'updated_by_name' => $request->user()->name,
        ]);

        return redirect()->route('contract-library.index')->with('success', 'Cláusula atualizada. Os contratos que já a usam não mudam.');
    }

    public function destroy(Request $request, SignatureContractClause $signatureContractClause)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractClause->sector_id);

        $signatureContractClause->delete();

        return back()->with('success', 'Cláusula removida da biblioteca.');
    }

    /**
     * Editar o modelo (06/11/2026): o mesmo editor do contrato — cláusulas,
     * variáveis, considerandos, partes e quem assina —, sem os dados de
     * pessoa nem o preenchimento das variáveis (ContractService::modelOnly).
     * Sem salvar automático: grava no botão Salvar.
     */
    public function editModel(Request $request, SignatureContractModel $signatureContractModel, ContractRenderer $renderer)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractModel->sector_id);

        $conteudo = ContractContent::normalize($signatureContractModel->content ?? []);

        return view('signature.contracts.model-edit', [
            'model' => $signatureContractModel,
            'content' => $conteudo,
            'preview' => $renderer->html($conteudo, null),
            'problems' => $renderer->problems($conteudo, true),
            'outline' => $renderer->outline($conteudo),
            'tokens' => $renderer->availableTokens($conteudo),
            'establishments' => app(ContractClub::class)->establishmentOptions(),
            'variableTypes' => ContractVariables::LABELS,
            'commonVariables' => ContractVariables::COMMON,
            'variableUsage' => $renderer->variableUsage($conteudo)['usage'],
            'directors' => app(DirectorAreaService::class)->options(),
        ]);
    }

    public function updateModel(Request $request, SignatureContractModel $signatureContractModel, ContractService $contracts)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractModel->sector_id);

        $dados = $request->validate(['name' => ['required', 'string', 'max:150']], [
            'name.required' => 'Dê um nome ao modelo.',
        ]);

        $contracts->updateModel($signatureContractModel, $dados['name'], $this->modelInput($request), $request->user()->name);

        return redirect()->route('contract-library.models.edit', ['signatureContractModel' => $signatureContractModel, 'passo' => max(0, min(3, (int) $request->input('passo', 0)))])
            ->with('success', 'Modelo salvo. Os contratos já feitos com ele não mudam; os novos saem assim.');
    }

    /** A prévia do modelo que está no formulário, sem gravar (como ContractController::preview). */
    public function previewModel(Request $request, SignatureContractModel $signatureContractModel, ContractRenderer $renderer)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractModel->sector_id);

        $conteudo = ContractContent::normalize($this->modelInput($request));
        $uso = $renderer->variableUsage($conteudo);

        return response()->json([
            'html' => $renderer->html($conteudo, null),
            'problems' => $renderer->problems($conteudo, true),
            'outline' => collect($renderer->outline($conteudo))
                ->map(fn(array $o, string $uid) => ['uid' => $uid] + $o)
                ->values(),
            'tokens' => $renderer->availableTokens($conteudo),
            'variables' => array_column($conteudo['variables'] ?? [], 'key'),
            'usage' => (object) $uso['usage'],
            'unknown' => $uso['unknown'],
        ]);
    }

    /** As cláusulas da biblioteca do setor do modelo, para o "Da biblioteca…" do editor. */
    public function modelClauses(Request $request, SignatureContractModel $signatureContractModel)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractModel->sector_id);

        return response()->json($this->clausesOf($signatureContractModel->sector_id));
    }

    public function destroyModel(Request $request, SignatureContractModel $signatureContractModel)
    {
        $this->authorize('manageLibrary', SignatureContract::class);
        $this->ensureVisible($request, $signatureContractModel->sector_id);

        $signatureContractModel->delete();

        return back()->with('success', 'Modelo removido. Os contratos feitos com ele não mudam.');
    }

    private function validated(Request $request): array
    {
        $setores = $this->writableSectors($request)->pluck('id')->join(',');

        return $request->validate([
            'sector_id' => ['required', 'integer', 'in:' . $setores],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:' . ContractContent::MAX_ITEMS],
            'items.*.html' => ['nullable', 'string', 'max:20000'],
            'items.*.level' => ['nullable', 'integer', 'in:1,2'],
        ], [
            'sector_id.in' => 'Escolha um dos seus setores.',
            'items.required' => 'A cláusula precisa de ao menos um item de texto.',
        ]);
    }

    /**
     * Os setores cujos itens o usuário vê: os dele; null = todos (Gerência).
     *
     * @return array<int, int>|null
     */
    private function visibleSectorIds(Request $request): ?array
    {
        return $request->user()->isManagementMember() ? null : $request->user()->sectorIds();
    }

    /** Onde o usuário pode gravar: os setores dele. */
    private function writableSectors(Request $request)
    {
        $ids = $request->user()->sectorIds();

        return $ids === [] ? collect() : Sector::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    private function ensureVisible(Request $request, ?int $sectorId): void
    {
        $visiveis = $this->visibleSectorIds($request);

        abort_unless($visiveis === null || in_array((int) $sectorId, $visiveis, true), 403);
    }

    private function findVisible(Request $request, int $id): ?SignatureContractClause
    {
        $clausula = SignatureContractClause::find($id);

        if (!$clausula) {
            return null;
        }

        $visiveis = $this->visibleSectorIds($request);

        return $visiveis === null || in_array((int) $clausula->sector_id, $visiveis, true) ? $clausula : null;
    }
}
