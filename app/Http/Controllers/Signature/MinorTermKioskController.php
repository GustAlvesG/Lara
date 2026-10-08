<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Signature\Concerns\RespondsWithKioskSession;
use App\Http\Middleware\EnsureMinorTermDevice;
use App\Models\SignatureKioskDevice;
use App\Models\SignatureMinorTerm;
use App\Services\Signature\MinorTerms\KioskDeviceService;
use App\Services\Signature\MinorTerms\MinorTermException;
use App\Services\Signature\MinorTerms\MinorTermService;
use App\Services\Signature\SignatureSigningDataService;
use App\Exceptions\SignatureSessionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O tablet de autoatendimento do Termo de Menores. Rota pública, mas só
 * responde a tablet pareado (middleware `signature_minor_device`).
 *
 * A tela é a do quiosque de assinatura (`quiosque.index`) no modo "menores":
 * as telas novas (título, responsável, CPF, menor, tela verde) vêm antes e
 * depois; a leitura, o aceite, o traço e a foto são os do quiosque. Gerado o
 * documento, o tablet recebe o mesmo cookie `lara_sign` de uma leitura de QR,
 * e dali em diante conversa com as rotas `quiosque.*` de sempre.
 */
class MinorTermKioskController extends Controller
{
    use RespondsWithKioskSession;

    public function __construct(
        private MinorTermService $terms,
        private KioskDeviceService $devices,
        private SignatureSigningDataService $signingData,
    ) {
    }

    public function index(Request $request)
    {
        $device = $this->devices->fromCookie($request->cookie(KioskDeviceService::COOKIE), $request->ip());

        return view('quiosque.index', [
            'modo' => 'menores',
            'pareado' => $device !== null,
        ]);
    }

    /** O tablet leu o QR de pareamento (ou digitou o código). */
    public function pair(Request $request): JsonResponse
    {
        $request->validate([
            'payload' => ['required_without:code', 'nullable', 'string', 'max:200'],
            'code' => ['required_without:payload', 'nullable', 'string', 'max:40'],
        ]);

        try {
            $pareamento = $this->devices->pair($request->input('payload'), $request->input('code'), $request->ip());
        } catch (MinorTermException $e) {
            return $this->fail($e);
        }

        return response()
            ->json($this->deviceState($pareamento['device']))
            ->cookie($this->deviceCookie($pareamento['cookie']));
    }

    /** Tablet pareado e termo do dia — a tela inicial pergunta a cada pouco. */
    public function state(Request $request): JsonResponse
    {
        return response()->json($this->deviceState($this->device($request)));
    }

    public function title(Request $request): JsonResponse
    {
        $dados = $request->validate(['title' => ['required', 'string', 'max:20']]);

        try {
            return response()->json($this->terms->startTitle($this->device($request), $dados['title']));
        } catch (MinorTermException $e) {
            return $this->fail($e);
        }
    }

    public function responsible(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'person' => ['required', 'integer'],
            'cpf' => ['required', 'string', 'max:20'],
        ]);

        try {
            return response()->json(
                $this->terms->confirmResponsible($this->device($request), (int) $dados['person'], $dados['cpf'])
            );
        } catch (MinorTermException $e) {
            return $this->fail($e);
        }
    }

    /** A lista de menores de novo — o "Autorizar outro menor". */
    public function minors(Request $request): JsonResponse
    {
        try {
            return response()->json($this->terms->minors($this->device($request)));
        } catch (MinorTermException $e) {
            return $this->fail($e);
        }
    }

    /**
     * Gera o documento do menor escolhido e abre a sessão do tablet — a mesma
     * resposta de `quiosque.consume`, com o cookie `lara_sign`.
     */
    public function document(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'minor' => ['required', 'integer'],
            'responsible_email' => ['nullable', 'string', 'max:150'],
            'responsible_rg' => ['nullable', 'string', 'max:40'],
            'minor_cpf' => ['nullable', 'string', 'max:20'],
            'minor_rg' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $resultado = $this->terms->createDocument(
                $this->device($request),
                (int) $dados['minor'],
                [
                    'responsible_email' => $dados['responsible_email'] ?? null,
                    'responsible_rg' => $dados['responsible_rg'] ?? null,
                    'minor_cpf' => $dados['minor_cpf'] ?? null,
                    'minor_rg' => $dados['minor_rg'] ?? null,
                ],
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            );
        } catch (MinorTermException $e) {
            return $this->fail($e);
        } catch (SignatureSessionException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        if ($resultado['already_authorized']) {
            return response()->json(['already_authorized' => true]);
        }

        return response()
            ->json($this->sessionPayload($resultado['request']) + ['already_authorized' => false])
            ->cookie($this->sessionCookie($resultado['session_token']));
    }

    /** "Concluir": encerra o atendimento deste tablet. */
    public function end(Request $request): JsonResponse
    {
        $this->terms->endFlow($this->device($request));

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceState(SignatureKioskDevice $device): array
    {
        $termo = SignatureMinorTerm::current();

        return [
            'paired' => true,
            'device' => $device->label(),
            'paired_until' => $device->expires_at?->format('d/m/Y H:i'),
            'term' => $termo ? ['name' => $termo->name, 'period' => $termo->periodLabel()] : null,
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function device(Request $request): SignatureKioskDevice
    {
        return $request->attributes->get(EnsureMinorTermDevice::ATTRIBUTE);
    }

    private function fail(MinorTermException $e): JsonResponse
    {
        return response()->json([
            'error' => $e->getMessage(),
            'restart' => $e->restart,
        ], $e->status);
    }

    /**
     * O cookie do tablet pareado: httpOnly, SameSite=Strict, só no caminho do
     * autoatendimento, vivo pelo prazo do pareamento (12 h).
     */
    private function deviceCookie(string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->make(
            name: KioskDeviceService::COOKIE,
            value: $value,
            minutes: $this->devices->ttlHours() * 60,
            path: parse_url(route('quiosque.menores.index'), PHP_URL_PATH) ?: '/',
            domain: null,
            secure: (bool) config('session.secure', false) || request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }
}
