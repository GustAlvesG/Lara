<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Exceptions\UnreadablePdfException;
use App\Http\Requests\StoreSignatureDocumentRequest;
use App\Http\Requests\StoreUploadedSignatureDocumentRequest;
use App\Models\Member;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureMemberDirectory;
use App\Support\Cpf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A tela do ATENDENTE: prepara o documento, confere com a pessoa na frente,
 * congela e acompanha a assinatura.
 *
 * O congelamento é a fronteira. Antes dele o documento é rascunho e muda à
 * vontade; depois, nem o texto nem os dados mudam, e o PDF que o tablet exibe
 * é o mesmo arquivo cujo hash ficou gravado.
 */
class DocumentController extends Controller
{
    // O Controller base deste projeto é vazio; quem usa `authorize()` aplica a
    // trait, como em Cotacao\MapaController.
    use AuthorizesRequests;

    public function __construct(private SignatureDocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', SignatureDocument::class);

        $status = $request->query('status');
        $busca = trim((string) $request->query('q', ''));

        $documentos = SignatureDocument::with(['template', 'signers'])
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($busca !== '', function ($query) use ($busca) {
                $digitos = Cpf::digits($busca);

                $query->where(function ($q) use ($busca, $digitos) {
                    $q->where('title', 'like', '%' . $busca . '%')
                        ->orWhere('validation_code', mb_strtoupper($busca))
                        ->orWhereHas('signers', function ($s) use ($busca, $digitos) {
                            $s->where('name', 'like', '%' . $busca . '%');

                            if ($digitos !== '') {
                                $s->orWhere('cpf', $digitos);
                            }
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('signature.documents.index', [
            'documents' => $documentos,
            'status' => $status,
            'busca' => $busca,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SignatureDocument::class);

        $modelos = SignatureTemplate::where('active', true)->orderBy('name')->get();

        $escolhido = $request->filled('template')
            ? $modelos->firstWhere('id', (int) $request->query('template'))
            : null;

        return view('signature.documents.create', [
            'templates' => $modelos,
            'template' => $escolhido,
        ]);
    }

    public function store(StoreSignatureDocumentRequest $request)
    {
        $this->authorize('create', SignatureDocument::class);

        $dados = $request->validated();

        $modelo = SignatureTemplate::findOrFail($dados['signature_template_id']);

        $documento = $this->documents->create(
            $modelo,
            [
                'title' => $dados['title'] ?? null,
                'data' => $request->fieldData(),
                'signer_field_keys' => $request->signerFieldKeys(),
                'attachment_requirements' => $request->attachmentRequirements(),
            ],
            $dados['signers'],
            auth()->id(),
            auth()->user()?->name,
        );

        return redirect()->route('signature-documents.show', $documento)
            ->with('success', 'Documento criado. Confira o texto com a pessoa antes de congelar.');
    }

    /**
     * Formulário de envio de um documento PRONTO, em PDF.
     *
     * O modelo vazio é só para o formulário de signatários, que é o mesmo do
     * documento de modelo: sem campos e sem partes, ele pede título, local e
     * quem assina.
     */
    public function createUpload()
    {
        $this->authorize('create', SignatureDocument::class);

        return view('signature.documents.upload', [
            'template' => new SignatureTemplate(['name' => '']),
        ]);
    }

    public function storeUpload(StoreUploadedSignatureDocumentRequest $request)
    {
        $this->authorize('create', SignatureDocument::class);

        $dados = $request->validated();

        try {
            $documento = $this->documents->createFromUpload(
                (string) file_get_contents($request->file('file')->getRealPath()),
                [
                    'title' => $dados['title'],
                    'attachment_requirements' => $request->attachmentRequirements(),
                ],
                [
                    'identity_check' => $dados['identity_check'],
                    'requires_photo' => $dados['requires_photo'] ?? false,
                    'requires_initials' => $dados['requires_initials'] ?? false,
                ],
                $dados['signers'],
                auth()->id(),
                auth()->user()?->name,
            );
        } catch (UnreadablePdfException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return redirect()->route('signature-documents.show', $documento)
            ->with('success', 'Documento enviado. Marque o lugar de cada assinatura e confira antes de congelar.');
    }

    /**
     * Grava onde cada pessoa assina no PDF enviado.
     */
    public function positions(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('update', $signatureDocument);

        abort_unless($signatureDocument->isUploaded(), 404);

        $dados = $request->validate([
            'positions' => ['present', 'array', 'max:10'],
            'positions.*' => ['nullable', 'array'],
            'positions.*.page' => ['required_with:positions.*', 'integer', 'min:1', 'max:2000'],
            'positions.*.x' => ['required_with:positions.*', 'numeric', 'between:0,1'],
            'positions.*.y' => ['required_with:positions.*', 'numeric', 'between:0,1'],
        ]);

        try {
            $this->documents->setSignaturePositions($signatureDocument, $dados['positions']);
        } catch (SignatureDocumentLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('view', $signatureDocument);

        $signatureDocument->load(['template', 'signers.requests', 'signers.evidence', 'signers.govbrCheck', 'signers.govbrInvites', 'govbrChecks', 'attachments', 'reviews']);

        $aba = in_array($request->query('aba'), ['govbr', 'revisao'], true) ? $request->query('aba') : 'documento';

        // Revisão: só de documento concluído. O motivo de não poder revisar
        // vai à tela — o prazo e "você acompanhou" são regra, não 403.
        $podeRevisar = $request->user()->can('review', $signatureDocument);

        return view('signature.documents.show', [
            'document' => $signatureDocument,
            'events' => $signatureDocument->auditEvents()->get(),
            // A aba vem pelo endereço: o envio do PDF do gov.br e a revisão
            // voltam direto para a aba deles, com o resultado na tela.
            'aba' => $aba === 'revisao' && $signatureDocument->status !== SignatureDocument::STATUS_FINALIZED ? 'documento' : $aba,
            'podeRevisar' => $podeRevisar,
            'bloqueioRevisao' => $podeRevisar
                ? app(\App\Services\Signature\SignatureReviewService::class)->blockReason(
                    $signatureDocument,
                    $request->user()->id,
                    $request->user()->can(\App\Authorization\Permissions::ASSINATURA_REVISAR_COORDENACAO),
                )
                : null,
        ]);
    }

    public function edit(SignatureDocument $signatureDocument)
    {
        $this->authorize('update', $signatureDocument);

        $signatureDocument->load(['template', 'signers']);

        return view('signature.documents.edit', [
            'document' => $signatureDocument,
            'template' => $signatureDocument->template,
        ]);
    }

    public function update(StoreSignatureDocumentRequest $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('update', $signatureDocument);

        $dados = $request->validated();

        try {
            $this->documents->update(
                $signatureDocument,
                [
                    'title' => $dados['title'] ?? null,
                    'data' => $request->fieldData(),
                    'signer_field_keys' => $request->signerFieldKeys(),
                    'attachment_requirements' => $request->attachmentRequirements(),
                ],
                $dados['signers'],
            );
        } catch (SignatureDocumentLockedException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('signature-documents.show', $signatureDocument)
            ->with('success', 'Documento atualizado.');
    }

    /**
     * Congela: gera o PDF, grava o hash e libera para o tablet.
     *
     * É irreversível de propósito. A partir daqui, corrigir alguma coisa é
     * cancelar e emitir outro documento — que é o que um papel assinado também
     * exigiria.
     */
    public function freeze(SignatureDocument $signatureDocument)
    {
        $this->authorize('release', $signatureDocument);

        try {
            $this->documents->freeze($signatureDocument, auth()->id());
        } catch (SignatureDocumentLockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('signature-documents.show', $signatureDocument)
            ->with('success', 'Documento congelado. Agora é possível liberar a assinatura no tablet.');
    }

    public function cancel(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('cancel', $signatureDocument);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->documents->cancel($signatureDocument, $request->input('reason'), auth()->id());
        } catch (SignatureDocumentLockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('signature-documents.show', $signatureDocument)
            ->with('success', 'Documento cancelado. Qualquer sessão aberta no tablet foi encerrada.');
    }

    /**
     * Entrega o PDF por ROTA, e nunca por URL de disco.
     *
     * Dois motivos, os mesmos do trait que serve as assinaturas dos contratos
     * de freelancer: é prova documental ligada ao CPF de uma pessoa e não deve
     * ficar adivinhável por URL; e este projeto não tem o link
     * `public/storage`, então uma URL de disco simplesmente não funcionaria.
     */
    public function pdf(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('download', $signatureDocument);

        $versao = $request->query('versao');
        $final = $versao === 'final';

        // `enviado` é o PDF como o atendente o mandou, antes de qualquer
        // carimbo — é o que a tela de marcar o lugar das assinaturas mostra.
        // `relatorio` é o relatório de validação do gov.br, à parte do final.
        $caminho = match ($versao) {
            'final' => $signatureDocument->final_path,
            'enviado' => $signatureDocument->source_path,
            'relatorio' => $signatureDocument->report_path,
            default => $signatureDocument->original_path,
        };

        abort_if(!$caminho, 404, 'Este documento ainda não tem PDF ' . ($final ? 'final' : 'original') . '.');

        $disk = Storage::disk(config('signature.disk'));

        abort_if(!$disk->exists($caminho), 404);

        return $disk->response($caminho, null, [
            'Content-Type' => 'application/pdf',
            // Documento pessoal: nunca em cache compartilhado.
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /**
     * Busca de associado para preencher o signatário.
     *
     * Aceita CPF (com ou sem máscara), título e nome. O CPF é comparado por
     * DÍGITOS dos dois lados: nos cadastros deste sistema o mesmo documento
     * aparece com e sem pontuação, e comparar texto cru já deixou passar gente
     * que estava cadastrada.
     *
     * A tabela local só tem quem se cadastrou no aplicativo. Por isso, quando
     * o termo tem cara de título, a família inteira — titular e dependentes —
     * vem do MultiClubes e entra na frente; quem dela também está na tabela
     * local aparece uma vez só, com o `id` local.
     */
    public function members(Request $request, SignatureMemberDirectory $directory)
    {
        $this->authorize('create', SignatureDocument::class);

        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 3) {
            return response()->json([]);
        }

        $digitos = Cpf::digits($termo);

        $associados = Member::query()
            ->where(function ($query) use ($termo, $digitos) {
                $query->where('Name', 'like', '%' . $termo . '%')
                    ->orWhere('title', $termo);

                if (strlen($digitos) >= 11) {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?",
                        [$digitos],
                    );
                }
            })
            ->orderBy('Name')
            ->limit(10)
            ->get(['Id', 'Name', 'cpf', 'title', 'Email', 'telephone']);

        $locais = $associados->map(fn(Member $member) => [
            'id' => $member->Id,
            'name' => $member->Name,
            // Só dígitos para o formulário; a tela mostra mascarado.
            'cpf' => Cpf::digits($member->cpf),
            'cpf_masked' => Cpf::mask($member->cpf),
            'title' => $member->title,
            'email' => $member->Email,
            'phone' => $member->telephone,
            // Titular ou dependente: a tabela local não sabe dizer.
            'kind' => null,
        ]);

        $familia = collect($directory->looksLikeTitle($termo) ? $directory->byTitle($termo) : []);

        if ($familia->isEmpty()) {
            return response()->json($locais->values());
        }

        $locaisPorCpf = $locais->filter(fn(array $local) => $local['cpf'] !== '')->keyBy('cpf');

        $daFamilia = $familia->map(function (array $pessoa) use ($locaisPorCpf) {
            $local = $pessoa['cpf'] !== '' ? $locaisPorCpf->get($pessoa['cpf']) : null;

            return [
                'id' => $local['id'] ?? null,
                'name' => $pessoa['name'],
                'cpf' => $pessoa['cpf'],
                // Dependente menor muitas vezes não tem CPF no cadastro.
                'cpf_masked' => $pessoa['cpf'] !== '' ? Cpf::mask($pessoa['cpf']) : 'sem CPF no cadastro',
                'title' => $pessoa['title'],
                // O contato que a pessoa pôs no aplicativo é mais recente que o do cadastro do clube.
                'email' => ($local['email'] ?? null) ?: $pessoa['email'],
                'phone' => ($local['phone'] ?? null) ?: $pessoa['phone'],
                'kind' => $pessoa['titular'] ? 'titular' : 'dependente',
            ];
        });

        $cpfsDaFamilia = $daFamilia->pluck('cpf')->filter()->all();

        return response()->json(
            $daFamilia
                ->concat($locais->reject(fn(array $local) => in_array($local['cpf'], $cpfsDaFamilia, true)))
                ->values(),
        );
    }
}
