<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\SignatureFormException;
use App\Exceptions\SignatureSessionException;
use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Services\Signature\SignatureAnswerCheckService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Conferência das respostas do tablet, do lado do atendente: o cartão da tela
 * do documento chama estas rotas por fetch e mostra o estado que volta (o
 * mesmo do polling de `ReleaseController::status`).
 *
 * As regras são do SignatureAnswerCheckService.
 */
class AnswerCheckController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SignatureAnswerCheckService $checks)
    {
    }

    public function approve(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('checkAnswers', $signatureDocument);

        return $this->respond($signatureDocument, fn() => $this->checks->approve(
            $signatureDocument,
            $request->user()?->id,
            $request->user()?->name,
        ));
    }

    public function giveBack(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('checkAnswers', $signatureDocument);

        $dados = $request->validate(
            ['note' => ['required', 'string', 'max:500']],
            ['note.required' => 'Escreva para a pessoa o que precisa ser corrigido.'],
        );

        return $this->respond($signatureDocument, fn() => $this->checks->giveBack(
            $signatureDocument,
            $dados['note'],
            $request->user()?->id,
            $request->user()?->name,
        ));
    }

    public function correct(Request $request, SignatureDocument $signatureDocument)
    {
        $this->authorize('checkAnswers', $signatureDocument);

        $dados = $request->validate(['answers' => ['present', 'array', 'max:60']]);

        return $this->respond($signatureDocument, fn() => $this->checks->correct(
            $signatureDocument,
            $dados['answers'],
            $request->user()?->id,
            $request->user()?->name,
        ));
    }

    private function respond(SignatureDocument $document, callable $acao)
    {
        try {
            $acao();
        } catch (SignatureFormException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json(['answer_check' => $this->checks->panelPayload($document->fresh())]);
    }
}
