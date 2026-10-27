<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\ContractException;
use App\Http\Controllers\Controller;
use App\Models\SignatureContractAttachment;
use App\Services\Signature\Contracts\ContractKioskService;
use App\Services\Signature\Contracts\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;

/**
 * "Validação de contratos" da Gerência no tablet do balcão — rota pública,
 * como o resto do quiosque, com entrada por matrícula e PIN (cookie
 * `lara_management`). Lê, comenta por cláusula e aprova ou devolve; ajustar
 * o texto é no computador. Regras e travas: ContractKioskService.
 */
class ContractKioskController extends Controller
{
    public function __construct(
        private ContractKioskService $kiosk,
        private ContractService $contracts,
    ) {
    }

    /** A página do tablet (o resto é JSON). */
    public function index()
    {
        return view('quiosque.gerencia', [
            'urls' => [
                'login' => route('quiosque.gerencia.login'),
                'pending' => route('quiosque.gerencia.pending'),
                'show' => route('quiosque.gerencia.show', ['contract' => '__ID__']),
                'decide' => route('quiosque.gerencia.decide', ['contract' => '__ID__']),
                'attachment' => route('quiosque.gerencia.attachment', ['contract' => '__ID__', 'attachment' => '__ANEXO__']),
                'logout' => route('quiosque.gerencia.logout'),
                'kiosk' => route('quiosque.index'),
            ],
            'idleSeconds' => max(60, (int) config('signature.directors.idle_seconds', 90) * 2),
        ]);
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
            $entrada = $this->kiosk->login($dados['matricula'], $dados['pin'], $request->ip());
        } catch (ContractException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()
            ->json(['user' => ['name' => $entrada['user']->name], 'contracts' => $this->kiosk->pending()])
            ->cookie($this->cookie($entrada['token']));
    }

    /** A lista — também o batimento que mantém a entrada viva. */
    public function pending(Request $request)
    {
        $user = $this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE));

        if (!$user) {
            return $this->ended();
        }

        return response()->json(['user' => ['name' => $user->name], 'contracts' => $this->kiosk->pending()]);
    }

    public function show(Request $request, int $contract)
    {
        if (!$this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE))) {
            return $this->ended();
        }

        try {
            return response()->json($this->kiosk->detail($contract));
        } catch (ContractException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function decide(Request $request, int $contract)
    {
        $user = $this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE));

        if (!$user) {
            return $this->ended();
        }

        $dados = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'note' => ['nullable', 'string', 'max:3000'],
            'comments' => ['nullable', 'array', 'max:60'],
            'comments.*' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $contrato = $this->kiosk->inReview($contract);

            if ($dados['decision'] === 'return') {
                $this->contracts->returnToAuthor($contrato, $dados['note'] ?? null, $dados['comments'] ?? [], $user->id, $user->name);

                return response()->json(['ok' => true, 'message' => 'Contrato devolvido. Quem redigiu foi avisado por e-mail.']);
            }

            $resultado = $this->contracts->approve($contrato, $dados['note'] ?? null, $user->id, $user->name);
        } catch (ContractException $e) {
            return response()->json(['error' => $e->getMessage(), 'problems' => array_column($e->problems, 'message')], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Contrato aprovado: ' . $resultado['contract']->number . '.'
                . ($resultado['document'] ? ' O documento de assinatura foi gerado em rascunho.' : ''),
            'warnings' => $resultado['warnings'],
        ]);
    }

    /** Anexo do contrato em validação (a proposta), aberto no tablet. */
    public function attachment(Request $request, int $contract, SignatureContractAttachment $attachment)
    {
        abort_unless($this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE)), 401);
        abort_unless($attachment->signature_contract_id === $contract, 404);

        try {
            $this->kiosk->inReview($contract);
        } catch (ContractException) {
            abort(404);
        }

        $disk = Storage::disk(config('signature.disk'));

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function logout(Request $request)
    {
        $this->kiosk->logout($request->cookie(ContractKioskService::COOKIE));

        return response()->json(['ok' => true])
            ->withCookie(Cookie::forget(ContractKioskService::COOKIE, $this->cookiePath()));
    }

    private function ended(): JsonResponse
    {
        return response()->json([
            'error' => 'A sua entrada no tablet terminou. Entre de novo com a matrícula e o PIN.',
            'ended' => true,
        ], 401)->withCookie(Cookie::forget(ContractKioskService::COOKIE, $this->cookiePath()));
    }

    /** Mesmo desenho do `lara_director`: httpOnly, SameSite=Strict, só no caminho do tablet. */
    private function cookie(string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->make(
            name: ContractKioskService::COOKIE,
            value: $value,
            minutes: $this->kiosk->sessionMinutes(),
            path: $this->cookiePath(),
            domain: null,
            secure: (bool) config('session.secure', false) || request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }

    private function cookiePath(): string
    {
        return parse_url(route('quiosque.index'), PHP_URL_PATH) ?: '/';
    }
}
