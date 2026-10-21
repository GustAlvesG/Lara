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
