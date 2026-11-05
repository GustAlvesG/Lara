<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\ClaudeException;
use App\Exceptions\ContractConflictException;
use App\Exceptions\ContractException;
use App\Http\Controllers\Controller;
use App\Models\SignatureContractAttachment;
use App\Services\Signature\Contracts\ContractAiReviewer;
use App\Services\Signature\Contracts\ContractApprovalInitials;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractKioskService;
use App\Services\Signature\Contracts\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * "Validação de contratos" da Gerência no tablet do balcão — rota pública,
 * como o resto do quiosque, com entrada por matrícula e PIN (cookie
 * `lara_management`). Lê, comenta por cláusula e aprova ou devolve. Desde
 * 04/11/2026 também pede a revisão pelo Claude (com trechos sensíveis e
 * trechos anexados, como no computador) e edita o título e o texto das
 * cláusulas. Regras e travas: ContractKioskService.
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
                'update' => route('quiosque.gerencia.update', ['contract' => '__ID__']),
                'aiReview' => route('quiosque.gerencia.ai-review', ['contract' => '__ID__']),
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
            // Visto da Gerência (04/11/2026): obrigatório para aprovar.
            'initials' => ['nullable', 'string', 'max:4000000'],
            'initials_strokes' => ['nullable', 'array', 'max:500'],
        ]);

        $vistos = app(ContractApprovalInitials::class);
        $visto = null;

        try {
            $contrato = $this->kiosk->inReview($contract);

            if ($dados['decision'] === 'return') {
                $this->contracts->returnToAuthor($contrato, $dados['note'] ?? null, $dados['comments'] ?? [], $user->id, $user->name);

                return response()->json(['ok' => true, 'message' => 'Contrato devolvido. Quem redigiu foi avisado por e-mail.']);
            }

            $visto = $vistos->store($contrato, $dados['initials'] ?? null, $dados['initials_strokes'] ?? null);
            $resultado = $this->contracts->approve($contrato, $dados['note'] ?? null, $user->id, $user->name, $visto);
        } catch (ContractException $e) {
            $vistos->discard($visto);

            return response()->json(['error' => $e->getMessage(), 'problems' => array_column($e->problems, 'message')], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Contrato aprovado: ' . $resultado['contract']->number . '.'
                . ($resultado['document'] ? ' O documento de assinatura foi gerado em rascunho.' : ''),
            'warnings' => $resultado['warnings'],
        ]);
    }

    /**
     * Ajuste do texto no tablet: título do contrato e título/itens das
     * cláusulas. Devolve o contrato atualizado (texto, problemas, revisão).
     */
    public function update(Request $request, int $contract)
    {
        $user = $this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE));

        if (!$user) {
            return $this->ended();
        }

        $dados = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'clauses' => ['required', 'array', 'max:' . ContractContent::MAX_CLAUSES],
            'clauses.*.uid' => ['required', 'string', 'max:40'],
            'clauses.*.title' => ['nullable', 'string', 'max:200'],
            'clauses.*.items' => ['nullable', 'array', 'max:' . ContractContent::MAX_ITEMS],
            'clauses.*.items.*.uid' => ['nullable', 'string', 'max:40'],
            'clauses.*.items.*.level' => ['nullable', 'integer', 'in:1,2'],
            'clauses.*.items.*.html' => ['nullable', 'string', 'max:20000'],
            // Salvar automático (05/11/2026): a revisão que o tablet abriu e, depois do aviso, gravar por cima.
            'revision' => ['nullable', 'integer', 'min:0'],
            'force' => ['nullable', 'boolean'],
            'auto' => ['nullable', 'boolean'],
        ]);

        try {
            $contrato = $this->kiosk->inReview($contract);
            $salvo = $this->kiosk->applyEdits(
                $contrato,
                $dados,
                $user->id,
                $user->name,
                isset($dados['revision']) ? (int) $dados['revision'] : null,
                (bool) ($dados['force'] ?? false),
            );

            return response()->json([
                'ok' => true,
                'message' => 'Texto ajustado. Na decisão, fica registrado como ajuste da Gerência.',
                'saved_at' => $salvo->content_saved_at?->format('H:i'),
                'contract' => $this->kiosk->detail($contract),
            ]);
        } catch (ContractConflictException $e) {
            return response()->json(['error' => $e->getMessage(), 'conflict' => true], 409);
        } catch (ContractException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * Revisão automática pelo Claude, pedida do tablet: os mesmos trechos
     * sensíveis a incluir e os mesmos trechos anexados do computador.
     */
    public function aiReview(Request $request, int $contract, ContractAiReviewer $reviewer)
    {
        $user = $this->kiosk->fromToken($request->cookie(ContractKioskService::COOKIE));

        if (!$user) {
            return $this->ended();
        }

        if (!$reviewer->isAvailable()) {
            return response()->json(['error' => 'A revisão automática não está configurada (falta a chave da API do Claude).'], 409);
        }

        $dados = $request->validate([
            'include' => ['nullable', 'array', 'max:200'],
            'include.*' => ['string', 'max:80'],
            'extras' => ['nullable', 'array', 'max:' . ContractAiReviewer::MAX_EXTRAS],
            'extras.*.title' => ['nullable', 'string', 'max:' . ContractAiReviewer::MAX_EXTRA_TITLE],
            'extras.*.text' => ['nullable', 'string', 'max:' . ContractAiReviewer::MAX_EXTRA_TEXT],
        ]);

        // O mesmo limite do computador: por pessoa, não por aparelho.
        $limite = 'contract-ai-review:' . $user->id;

        if (RateLimiter::tooManyAttempts($limite, 6)) {
            return response()->json(['error' => 'Muitas análises seguidas. Espere um minuto e peça de novo.'], 429);
        }

        RateLimiter::hit($limite, 60);

        try {
            $contrato = $this->kiosk->inReview($contract);
            // No tablet só entra a Gerência: a revisão jurídica vem sempre.
            $resultado = $reviewer->review($contrato, $user->id, $user->name, $dados['include'] ?? [], $dados['extras'] ?? [], true);
        } catch (ContractException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (ClaudeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => $resultado['reused']
                ? 'O texto não mudou desde a última revisão: ela continua valendo.'
                : 'Revisão automática concluída. Confira os apontamentos antes de usar.',
            'ai' => $this->kiosk->aiPayload($contrato->fresh()),
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
