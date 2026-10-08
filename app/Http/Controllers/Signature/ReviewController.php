<?php

namespace App\Http\Controllers\Signature;

use App\Authorization\Permissions as P;
use App\Exceptions\SignatureDocumentLockedException;
use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Models\SignatureReview;
use App\Services\Signature\SignatureReviewService;
use App\Support\Cpf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Revisão interna dos documentos assinados: a fila (menu Assinaturas →
 * Revisão) e o registro, na aba "Revisão" do documento. As regras são do
 * SignatureReviewService.
 *
 * Recusa volta como "Atenção" (`warning`): é regra do processo, não falha.
 */
class ReviewController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SignatureReviewService $reviews)
    {
    }

    /**
     * A fila. "A revisar" traz os concluídos sem revisão em ordem — os ainda
     * no prazo e os que a pessoa acompanhou aparecem também, com o motivo,
     * para ninguém achar que sumiram. "Revisados" traz os já em ordem.
     */
    public function index(Request $request)
    {
        $situacao = $request->query('situacao') === 'revisados' ? 'revisados' : 'pendentes';
        $busca = trim((string) $request->query('q', ''));
        $usuario = $request->user();
        $coordenacao = $usuario->can(P::ASSINATURA_REVISAR_COORDENACAO);

        $documentos = SignatureDocument::with(['template', 'signers'])
            ->where('status', SignatureDocument::STATUS_FINALIZED)
            ->when(
                $situacao === 'revisados',
                fn($q) => $q->where('review_status', SignatureReview::RESULT_OK),
                fn($q) => $q->where(fn($q) => $q->whereNull('review_status')
                    ->orWhere('review_status', '!=', SignatureReview::RESULT_OK)),
            )
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
            // Pendentes: o mais antigo primeiro, é o que espera há mais tempo.
            ->orderBy($situacao === 'revisados' ? 'reviewed_at' : 'finalized_at', $situacao === 'revisados' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        $motivos = [];

        if ($situacao === 'pendentes') {
            foreach ($documentos as $documento) {
                $motivos[$documento->id] = $this->reviews->blockReason($documento, $usuario->id, $coordenacao);
            }
        }

        return view('signature.reviews.index', [
            'documents' => $documentos,
            'situacao' => $situacao,
            'busca' => $busca,
            'motivos' => $motivos,
            'coordenacao' => $coordenacao,
        ]);
    }

    public function store(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('review', $signatureDocument);

        $dados = $request->validate([
            'resultado' => ['required', 'string'],
            'feitos' => ['nullable', 'array'],
            'feitos.*' => ['string', 'max:80'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'resultado.required' => 'Escolha o resultado da revisão.',
        ]);

        try {
            $this->reviews->review(
                $signatureDocument,
                $dados['resultado'],
                array_values($dados['feitos'] ?? []),
                $dados['observacao'] ?? null,
                auth()->id(),
                auth()->user()?->name,
                $request->user()->can(P::ASSINATURA_REVISAR_COORDENACAO),
            );
        } catch (SignatureDocumentLockedException $e) {
            return redirect()->route('signature-documents.show', [$signatureDocument, 'aba' => 'revisao'])
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-documents.show', [$signatureDocument, 'aba' => 'revisao'])
            ->with('success', $dados['resultado'] === SignatureReview::RESULT_OK
                ? 'Revisão registrada: tudo em ordem.'
                : 'Revisão registrada com pendência. O documento continua na fila até ser revisado em ordem.');
    }
}
