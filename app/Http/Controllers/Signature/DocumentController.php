<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureDocumentRequest;
use App\Models\Member;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentService;
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
                'data' => $dados['data'] ?? [],
                'location' => $dados['location'] ?? null,
            ],
            $dados['signers'],
            auth()->id(),
        );

        return redirect()->route('signature-documents.show', $documento)
            ->with('success', 'Documento criado. Confira o texto com a pessoa antes de congelar.');
    }

    public function show(SignatureDocument $signatureDocument)
    {
        $this->authorize('view', $signatureDocument);

        $signatureDocument->load(['template', 'signers.requests', 'signers.evidence']);

        return view('signature.documents.show', [
            'document' => $signatureDocument,
            'events' => $signatureDocument->auditEvents()->get(),
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
                    'data' => $dados['data'] ?? [],
                    'location' => $dados['location'] ?? null,
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

        $final = $request->query('versao') === 'final';

        $caminho = $final ? $signatureDocument->final_path : $signatureDocument->original_path;

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
     */
    public function members(Request $request)
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

        return response()->json($associados->map(fn(Member $member) => [
            'id' => $member->Id,
            'name' => $member->Name,
            // Só dígitos para o formulário; a tela mostra mascarado.
            'cpf' => Cpf::digits($member->cpf),
            'cpf_masked' => Cpf::mask($member->cpf),
            'title' => $member->title,
            'email' => $member->Email,
            'phone' => $member->telephone,
        ]));
    }
}
