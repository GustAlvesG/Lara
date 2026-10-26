<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Signature\Concerns\RespondsWithKioskSession;
use App\Models\User;
use App\Services\Signature\Directors\DirectorException;
use App\Services\Signature\Directors\DirectorKioskService;
use App\Services\Signature\SignatureSigningDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * "Assinatura da Diretoria" no tablet do balcão — rota pública, como o resto
 * do quiosque.
 *
 * O diretor entra com matrícula e PIN do Lara, vê a lista dos documentos
 * pendentes da assinatura dele e abre um. A abertura devolve a mesma sessão
 * (`lara_sign`) que a leitura do QR devolve, e o tablet segue pelas rotas do
 * quiosque. Terminado, volta à lista enquanto a entrada do diretor valer.
 *
 * Regras e travas: DirectorKioskService.
 */
class DirectorKioskController extends Controller
{
    use RespondsWithKioskSession;

    public function __construct(
        private DirectorKioskService $directors,
        private SignatureSigningDataService $signingData,
    ) {
    }

    public function login(Request $request)
    {
        $dados = $request->validate([
            'matricula' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'string', 'max:20'],
        ], [
            'matricula.required' => 'Informe a matrícula.',
            'pin.required' => 'Informe o PIN.',
        ]);

        try {
            $entrada = $this->directors->login($dados['matricula'], $dados['pin'], $request->ip());
        } catch (DirectorException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()
            ->json($this->listPayload($entrada['user']))
            ->cookie($this->directorCookie($entrada['token']));
    }

    /** A lista do diretor — também o batimento que mantém a entrada viva. */
    public function pending(Request $request)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        return response()->json($this->listPayload($diretor));
    }

    public function open(Request $request, int $signatureSigner)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        try {
            $sessao = $this->directors->open($diretor, $signatureSigner, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (DirectorException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()
            ->json($this->sessionPayload($sessao['request']))
            ->cookie($this->sessionCookie($sessao['session_token']));
    }

    /*
     | Assinatura em lote: abrir, ler (PDF + leitura registrada), assinar ou
     | desistir. Tudo pela entrada do diretor (`lara_director`), sem `lara_sign`
     | — são várias liberações ao mesmo tempo. Regras: DirectorKioskService.
     */

    public function openBatch(Request $request)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        $dados = $request->validate([
            'signers' => ['required', 'array', 'min:1', 'max:' . DirectorKioskService::BATCH_MAX],
            'signers.*' => ['integer'],
        ]);

        try {
            $lote = $this->directors->openBatch($diretor, $dados['signers'], [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (DirectorException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json($lote + [
            'signer' => ['name' => $diretor->name],
            'warning_seconds' => (int) config('signature.session_warning_seconds', 60),
        ]);
    }

    public function batchPdf(Request $request, int $signatureSigner)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));
        $solicitacao = $diretor ? $this->directors->batchRequest($diretor, $signatureSigner) : null;

        abort_if(!$solicitacao, 404);

        $documento = $solicitacao->signer->document;
        $disk = \Illuminate\Support\Facades\Storage::disk(config('signature.disk'));

        abort_if(!$documento->original_path || !$disk->exists($documento->original_path), 404);

        return $disk->response($documento->original_path, null, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function batchViewed(Request $request, int $signatureSigner)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        $dados = $request->validate([
            'read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'scrolled_to_end' => ['nullable', 'boolean'],
        ]);

        try {
            $this->directors->batchViewed($diretor, $signatureSigner, $dados['read_seconds'] ?? null,
                (bool) ($dados['scrolled_to_end'] ?? false), ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]);
        } catch (DirectorException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true]);
    }

    public function signBatch(Request $request)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        // Os mesmos campos da assinatura avulsa (QuiosqueController::sign),
        // mais a lista e a leitura de cada documento.
        $dados = $request->validate([
            'signers' => ['required', 'array', 'min:1', 'max:' . DirectorKioskService::BATCH_MAX],
            'signers.*' => ['integer'],
            'reads' => ['nullable', 'array'],
            'reads.*.read_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'reads.*.scrolled_to_end' => ['nullable', 'boolean'],
            'signature' => ['required', 'string'],
            'strokes' => ['nullable', 'array'],
            'initials' => ['nullable', 'string'],
            'initials_strokes' => ['nullable', 'array'],
            'photo' => ['nullable', 'string'],
            'photo_skipped_reason' => ['nullable', 'string', 'max:60'],
            'photo_consent' => ['nullable', 'boolean'],
            'accepted' => ['required', 'accepted'],
            'viewport' => ['nullable', 'array'],
        ]);

        $resultado = $this->directors->signBatch($diretor, $dados['signers'], [
            'signature' => $dados['signature'],
            'strokes' => $dados['strokes'] ?? null,
            'initials' => $dados['initials'] ?? null,
            'initials_strokes' => $dados['initials_strokes'] ?? null,
            'photo' => $dados['photo'] ?? null,
            'photo_skipped_reason' => $dados['photo_skipped_reason'] ?? null,
            'photo_consent' => (bool) ($dados['photo_consent'] ?? false),
            'accepted' => true,
            'viewport' => $dados['viewport'] ?? null,
        ], $dados['reads'] ?? [], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'results' => $resultado,
            'signed' => collect($resultado)->where('ok', true)->count(),
            'signed_at' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function cancelBatch(Request $request)
    {
        $diretor = $this->directors->fromToken($request->cookie(DirectorKioskService::COOKIE));

        if (!$diretor) {
            return $this->ended();
        }

        $dados = $request->validate([
            'signers' => ['required', 'array', 'max:' . DirectorKioskService::BATCH_MAX],
            'signers.*' => ['integer'],
        ]);

        $this->directors->cancelBatch($diretor, $dados['signers']);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $this->directors->logout($request->cookie(DirectorKioskService::COOKIE));

        return response()->json(['ok' => true])
            ->withCookie(Cookie::forget(DirectorKioskService::COOKIE, $this->cookiePath()));
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(User $diretor): array
    {
        return [
            // Só o primeiro nome no cabeçalho: o tablet fica no balcão.
            'director' => ['name' => $diretor->name],
            'documents' => $this->directors->pendingPayload($diretor),
            // "Já assinados": para acompanhar o que falta e a via final.
            'signed' => $this->directors->signedPayload($diretor),
            'batch_max' => DirectorKioskService::BATCH_MAX,
            'session_minutes' => $this->directors->sessionMinutes(),
        ];
    }

    /** Entrada do diretor vencida ou inexistente: o tablet volta à espera. */
    private function ended(): JsonResponse
    {
        return response()->json([
            'error' => 'A sua entrada no tablet terminou. Entre de novo com a matrícula e o PIN.',
            'director_ended' => true,
        ], 401)->withCookie(Cookie::forget(DirectorKioskService::COOKIE, $this->cookiePath()));
    }

    /** Mesmo desenho do cookie `lara_sign`: httpOnly, SameSite=Strict, só no caminho do tablet. */
    private function directorCookie(string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->make(
            name: DirectorKioskService::COOKIE,
            value: $value,
            minutes: $this->directors->sessionMinutes(),
            path: $this->cookiePath(),
            domain: null,
            secure: (bool) config('session.secure', false) || request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }
}
