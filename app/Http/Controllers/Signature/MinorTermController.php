<?php

namespace App\Http\Controllers\Signature;

use App\Authorization\Permissions as P;
use App\Http\Controllers\Controller;
use App\Models\SignatureKioskDevice;
use App\Models\SignatureMinorAuthorization;
use App\Models\SignatureMinorTerm;
use App\Models\SignatureTemplate;
use App\Services\Signature\MinorTerms\KioskDeviceService;
use App\Services\Signature\MinorTerms\MinorTermFields;
use App\Services\Signature\SignatureQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Assinaturas → Termo de Menores, no computador: o termo de cada evento
 * (modelo + vigência), o pareamento do tablet de autoatendimento e o histórico
 * de autorizações — a tela de quem põe a pulseira, para quando a tela verde
 * do tablet já saiu.
 *
 * Cada tela tem a sua permissão (ver routes/web.php); o menu abre para quem
 * tem qualquer uma (Gate `acessar-termo-menores`).
 */
class MinorTermController extends Controller
{
    public function __construct(private KioskDeviceService $devices)
    {
    }

    /** O item do menu: cai na primeira tela que a pessoa alcança. */
    public function index(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        return match (true) {
            $usuario->can(P::ASSINATURA_TERMO_MENORES_HISTORICO) => redirect()->route('minor-terms.history'),
            $usuario->can(P::ASSINATURA_TERMO_MENORES_GERENCIAR) => redirect()->route('minor-terms.terms'),
            default => redirect()->route('minor-terms.devices'),
        };
    }

    /* ------------------------------------------------------------------
     | Termos (um por evento)
     |------------------------------------------------------------------*/

    public function terms()
    {
        $termos = SignatureMinorTerm::with('template')
            ->withCount(['authorizations as authorized_count' => fn($q) => $q->authorized()])
            ->orderByDesc('starts_on')
            ->paginate(20);

        return view('signature.minor-terms.terms', [
            'terms' => $termos,
            'templates' => $this->templateOptions(),
            'current' => SignatureMinorTerm::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validated($request);

        SignatureMinorTerm::create($dados + [
            'created_by' => $request->user()->id,
            'created_by_name' => $request->user()->name,
        ]);

        return redirect()->route('minor-terms.terms')->with('success', 'Termo cadastrado.');
    }

    public function edit(SignatureMinorTerm $signatureMinorTerm)
    {
        return view('signature.minor-terms.edit', [
            'term' => $signatureMinorTerm,
            'templates' => $this->templateOptions(),
        ]);
    }

    public function update(Request $request, SignatureMinorTerm $signatureMinorTerm): RedirectResponse
    {
        $dados = $this->validated($request, $signatureMinorTerm);

        $signatureMinorTerm->update($dados);

        return redirect()->route('minor-terms.terms')->with('success', 'Termo atualizado.');
    }

    /**
     * Nome, modelo e vigência. O modelo precisa servir ao autoatendimento
     * (MinorTermFields::templateProblems) e a vigência não pode cruzar a de
     * outro termo ativo — um vigente por vez é o que dispensa o tablet de
     * perguntar "qual evento".
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(Request $request, ?SignatureMinorTerm $term = null): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'signature_template_id' => ['required', 'integer', 'exists:signature_templates,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'active' => ['nullable', 'boolean'],
        ], [
            'ends_on.after_or_equal' => 'O fim da vigência não pode ser antes do início.',
        ], [
            'name' => 'nome do evento',
            'signature_template_id' => 'modelo',
            'starts_on' => 'início da vigência',
            'ends_on' => 'fim da vigência',
        ]);

        $modelo = SignatureTemplate::findOrFail($dados['signature_template_id']);

        if ($modelo->single_use) {
            throw ValidationException::withMessages(['signature_template_id' => 'Escolha um modelo da lista de Modelos.']);
        }

        $problemas = MinorTermFields::templateProblems($modelo->currentVersion());

        if ($problemas !== []) {
            throw ValidationException::withMessages(['signature_template_id' => $problemas]);
        }

        $ativo = $term === null ? true : $request->boolean('active');

        if ($ativo) {
            $cruza = SignatureMinorTerm::overlapping($dados['starts_on'], $dados['ends_on'], $term?->id)->first();

            if ($cruza) {
                throw ValidationException::withMessages([
                    'starts_on' => 'A vigência cruza a do termo "' . $cruza->name . '" (' . $cruza->periodLabel() . '). '
                        . 'Só um termo pode estar disponível por vez.',
                ]);
            }
        }

        return [
            'name' => trim($dados['name']),
            // A raiz das versões: o documento sai sempre da versão vigente.
            'signature_template_id' => $modelo->root_id ?? $modelo->id,
            'starts_on' => $dados['starts_on'],
            'ends_on' => $dados['ends_on'],
            'active' => $ativo,
        ];
    }

    /**
     * Os modelos que o cadastro oferece: a versão vigente de cada um, com o
     * que impede o uso no autoatendimento (vazio = serve).
     *
     * @return \Illuminate\Support\Collection<int, array{template: SignatureTemplate, problems: array<int, string>}>
     */
    private function templateOptions()
    {
        return SignatureTemplate::where('active', true)
            ->where('single_use', false)
            ->orderBy('name')
            ->get()
            ->map(fn(SignatureTemplate $t) => ['template' => $t, 'problems' => MinorTermFields::templateProblems($t)]);
    }

    /* ------------------------------------------------------------------
     | Tablets
     |------------------------------------------------------------------*/

    public function devices()
    {
        $tablets = SignatureKioskDevice::whereNotNull('paired_at')
            ->orderByDesc('paired_at')
            ->limit(30)
            ->get();

        return view('signature.minor-terms.devices', [
            'devices' => $tablets,
            'ttlHours' => $this->devices->ttlHours(),
            'pairingSeconds' => (int) config('signature.minor_terms.pairing_ttl_seconds', 300),
            'tabletUrl' => route('quiosque.menores.index'),
        ]);
    }

    /**
     * Gera o QR de pareamento. O conteúdo em claro só existe nesta resposta;
     * o QR é desenhado no servidor.
     */
    public function startPairing(Request $request, SignatureQrCode $qr): JsonResponse
    {
        $dados = $request->validate(['name' => ['nullable', 'string', 'max:80']]);

        $pareamento = $this->devices->startPairing($dados['name'] ?? null, $request->user()->id, $request->user()->name);

        return response()->json([
            'device' => $pareamento['device']->id,
            // Fundo branco e módulos pretos fixos (SignatureQrCode): legível
            // pela câmera também no tema escuro.
            'qr_src' => $qr->dataUri($pareamento['payload'], 260),
            'manual_code' => $pareamento['manual_code'],
            'expires_in' => (int) config('signature.minor_terms.pairing_ttl_seconds', 300),
            'status_url' => route('minor-terms.devices.status', $pareamento['device']),
        ]);
    }

    public function deviceStatus(SignatureKioskDevice $signatureKioskDevice): JsonResponse
    {
        return response()->json([
            'paired' => $signatureKioskDevice->isActive(),
            'awaiting' => $signatureKioskDevice->isAwaitingPairing(),
            'status' => $signatureKioskDevice->statusLabel(),
        ]);
    }

    public function revoke(Request $request, SignatureKioskDevice $signatureKioskDevice): RedirectResponse
    {
        $this->devices->revoke($signatureKioskDevice, $request->user()->name);

        return redirect()->route('minor-terms.devices')
            ->with('success', $signatureKioskDevice->label() . ' despareado: ele para de gerar termos na próxima tela.');
    }

    /* ------------------------------------------------------------------
     | Histórico
     |------------------------------------------------------------------*/

    /**
     * Quem foi autorizado, para conferir na entrada. Por padrão, o termo
     * vigente (ou o mais recente) e só os assinados.
     */
    public function history(Request $request)
    {
        $termos = SignatureMinorTerm::orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on', 'active']);

        $padrao = SignatureMinorTerm::current()?->id ?? $termos->first()?->id;
        $termoId = $request->filled('termo') ? (int) $request->query('termo') : $padrao;
        $situacao = $request->query('situacao') === 'todos' ? 'todos' : 'autorizados';
        $busca = trim((string) $request->query('q', ''));

        $autorizacoes = SignatureMinorAuthorization::with(['term', 'document.signers.evidence'])
            ->when($termoId, fn($q) => $q->where('signature_minor_term_id', $termoId))
            ->when($situacao === 'autorizados', fn($q) => $q->authorized())
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($q) use ($busca) {
                    $q->where('minor_name', 'like', '%' . $busca . '%')
                        ->orWhere('responsible_name', 'like', '%' . $busca . '%')
                        ->orWhere('title_code', $busca);
                });
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('signature.minor-terms.history', [
            'authorizations' => $autorizacoes,
            'terms' => $termos,
            'termId' => $termoId,
            'situacao' => $situacao,
            'canSeeDocument' => $request->user()->can('acessar-documentos-assinatura'),
        ]);
    }

    /**
     * A foto do responsável, tirada na assinatura — é o que a entrada confere.
     * Servida por rota (disco privado), só para quem vê o histórico.
     */
    public function photo(SignatureMinorAuthorization $signatureMinorAuthorization)
    {
        $caminho = $signatureMinorAuthorization->document?->signers()->first()?->evidence?->photo_path;

        abort_if(!$caminho, 404);

        $disco = Storage::disk(config('signature.disk'));

        abort_if(!$disco->exists($caminho), 404);

        return $disco->response($caminho, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
