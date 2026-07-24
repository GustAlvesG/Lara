<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\FreelancerServiceLockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreelancerRequest;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Kiosk de assinatura de contratos de freelancer, para uso em tablet — substitui
 * o bot do Telegram por uma interface de toque.
 *
 * Autenticação PRÓPRIA, independente da sessão web: o operador entra com
 * matrícula + PIN de 6 dígitos e recebe uma sessão de kiosk (guardada no lado
 * do servidor). Só usuários com a permissão `manage freelancers` operam. O
 * operador identificado fica gravado em created_by / freelancer_signed_by, e o
 * PIN é reconfirmado a cada assinatura — espelhando a reconfirmação de senha
 * que o bot fazia pela API.
 *
 * A sessão de atendimento dura 30 minutos OU 5 contratos registrados, o que
 * vier primeiro; passado o limite, o operador entra de novo.
 */
class KioskController extends Controller
{
    private const SESSION_MINUTES = 30;
    private const SESSION_MAX_CONTRACTS = 5;

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index()
    {
        return view('kiosk.index');
    }

    /* ---------------------------------------------------------------------
     | Autenticação do operador (matrícula + PIN)
     |---------------------------------------------------------------------*/

    /** Estado atual da sessão — permite retomar ao recarregar a página. */
    public function session()
    {
        if (!$this->sessionActive()) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'operator' => $this->operatorPayload($this->currentOperator()),
            'session' => $this->sessionPayload(),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'matricula' => ['required', 'string'],
            'pin' => ['required', 'digits:6'],
        ]);

        $user = User::where('matricula', $request->input('matricula'))->first();

        if (!$user || (int) $user->status_id !== 1) {
            return response()->json(['error' => 'Matrícula não encontrada ou usuário inativo.'], 401);
        }

        if (!$user->can('manage freelancers')) {
            return response()->json(['error' => 'Este usuário não tem acesso ao kiosk.'], 403);
        }

        if (!$user->hasPin()) {
            return response()->json(['error' => 'Usuário sem PIN. Defina um PIN de 6 dígitos no painel (Usuários).'], 422);
        }

        if (!$user->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        session([
            'kiosk.operator_id' => $user->id,
            'kiosk.started_at' => now()->timestamp,
            'kiosk.count' => 0,
        ]);

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'operator' => $this->operatorPayload($user),
            'session' => $this->sessionPayload(),
        ]);
    }

    public function logout()
    {
        $this->clearSession();

        return response()->json(['ok' => true]);
    }

    /* ---------------------------------------------------------------------
     | Freelancer
     |---------------------------------------------------------------------*/

    public function findFreelancer(string $cpf)
    {
        $this->operatorOrFail();

        $freelancer = Freelancer::where('cpf', $cpf)->first();

        if (!$freelancer) {
            return response()->json(['found' => false], 404);
        }

        return response()->json(['found' => true, 'freelancer' => $this->freelancerPayload($freelancer)]);
    }

    public function storeFreelancer(StoreFreelancerRequest $request)
    {
        $operator = $this->operatorOrFail();

        $data = $request->validated();
        $data['created_by'] = $operator->id;

        $freelancer = $this->freelancerService->create($data);
        $freelancer->forceFill(['created_by' => $operator->id, 'updated_by' => $operator->id])->save();

        return response()->json(['freelancer' => $this->freelancerPayload($freelancer)], 201);
    }

    /* ---------------------------------------------------------------------
     | Funções e serviços
     |---------------------------------------------------------------------*/

    public function functions()
    {
        $this->operatorOrFail();

        return response()->json(
            FunctionFreelancer::orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn(FunctionFreelancer $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'price' => (float) $f->price,
                ])
        );
    }

    /** Só os contratos que ainda aceitam a assinatura do freelancer. */
    public function services(Freelancer $freelancer)
    {
        $this->operatorOrFail();

        $services = $freelancer->freelancerServices()
            ->with('functionFreelancer')
            ->orderByDesc('start_date')
            ->get()
            ->filter(fn(FreelancerService $s) => $s->canBeSignedByFreelancer())
            ->values()
            ->map(fn(FreelancerService $s) => $this->servicePayload($s));

        return response()->json($services);
    }

    public function storeService(StoreFreelancerServiceRequest $request)
    {
        $operator = $this->operatorOrFail();

        $data = $request->validated();
        $data['created_by'] = $operator->id;

        // Limite semanal: primeiro toque devolve 409 pedindo confirmação; o
        // reenvio exige confirm_weekly_limit + o PIN do operador.
        if (FreelancerService::wouldExceedWeeklyLimit($data['freelancer_id'], $data['start_date'])) {
            if (!$request->boolean('confirm_weekly_limit')) {
                return response()->json($this->weeklyLimitPayload($data), 409);
            }

            if (!$operator->checkPin($request->input('pin'))) {
                return response()->json(['error' => 'PIN inválido.'], 401);
            }
        }

        $service = $this->freelancerService->createService($data);
        $service->forceFill(['created_by' => $operator->id, 'updated_by' => $operator->id])->save();
        $this->bumpCount();

        return response()->json([
            'service' => $this->servicePayload($service->load('functionFreelancer')),
            'session' => $this->sessionPayload(),
        ], 201);
    }

    /**
     * Assinatura do freelancer: exige o PIN do operador (reconfirmado a cada
     * assinatura) e a imagem do traço desenhado sobre o documento. É definitiva.
     */
    public function signService(Request $request, FreelancerService $freelancerService)
    {
        $operator = $this->operatorOrFail();

        $request->validate([
            'pin' => ['required', 'digits:6'],
            'signature' => ['required', 'string'],
        ]);

        if (!$operator->checkPin($request->input('pin'))) {
            return response()->json(['error' => 'PIN inválido.'], 401);
        }

        try {
            $path = $this->storeSignatureImage($freelancerService, $request->input('signature'));
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível salvar a assinatura. Tente novamente.'], 422);
        }

        try {
            $this->freelancerService->signAsFreelancer($freelancerService, $operator);
        } catch (FreelancerServiceLockedException $e) {
            Storage::disk('public')->delete($path);

            return response()->json(['error' => $e->getMessage()], 409);
        }

        $freelancerService->forceFill(['freelancer_signature_path' => $path])->save();

        return response()->json([
            'service' => $this->servicePayload($freelancerService->fresh()->load('functionFreelancer')),
            'session' => $this->sessionPayload(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Sessão do operador
     |---------------------------------------------------------------------*/

    private function currentOperator(): ?User
    {
        $id = session('kiosk.operator_id');

        return $id ? User::find($id) : null;
    }

    private function sessionActive(): bool
    {
        if (!session('kiosk.operator_id')) {
            return false;
        }

        $startedAt = session('kiosk.started_at');

        if (!$startedAt || now()->timestamp - $startedAt > self::SESSION_MINUTES * 60) {
            return false;
        }

        return session('kiosk.count', 0) < self::SESSION_MAX_CONTRACTS;
    }

    /** Garante uma sessão de operador válida e a devolve; senão, 419. */
    private function operatorOrFail(): User
    {
        if ($this->sessionActive()) {
            $operator = $this->currentOperator();

            if ($operator) {
                return $operator;
            }
        }

        $reason = session('kiosk.count', 0) >= self::SESSION_MAX_CONTRACTS
            ? 'Sessão encerrada: limite de ' . self::SESSION_MAX_CONTRACTS . ' contratos. Entre novamente.'
            : 'Sessão expirada (' . self::SESSION_MINUTES . ' min). Entre novamente.';

        $this->clearSession();

        throw new HttpResponseException(
            response()->json(['error' => $reason, 'expired' => true], 419)
        );
    }

    private function bumpCount(): void
    {
        session(['kiosk.count' => session('kiosk.count', 0) + 1]);
    }

    private function clearSession(): void
    {
        session()->forget(['kiosk.operator_id', 'kiosk.started_at', 'kiosk.count']);
    }

    private function sessionPayload(): array
    {
        $startedAt = session('kiosk.started_at', now()->timestamp);
        $elapsed = now()->timestamp - $startedAt;

        return [
            'remaining_seconds' => max(0, self::SESSION_MINUTES * 60 - $elapsed),
            'count' => session('kiosk.count', 0),
            'max' => self::SESSION_MAX_CONTRACTS,
        ];
    }

    /* ---------------------------------------------------------------------
     | Payloads
     |---------------------------------------------------------------------*/

    private function operatorPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'matricula' => $user->matricula];
    }

    private function freelancerPayload(Freelancer $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'cpf' => $f->cpf,
            'pix_key' => $f->pix_key ?: $f->cpf,
            'rg' => $f->rg,
            'nacionality' => $f->nacionality,
            'civil_status' => $f->civil_status,
            'address' => $f->address,
        ];
    }

    private function servicePayload(FreelancerService $s): array
    {
        return [
            'id' => $s->id,
            'function' => $s->functionFreelancer?->name,
            'function_id' => $s->function_freelancer_id,
            'location' => $s->location,
            'start_date' => $s->start_date?->toDateString(),
            'start_date_br' => $s->start_date ? Carbon::parse($s->start_date)->format('d/m/Y') : null,
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_date_br' => $s->end_date ? Carbon::parse($s->end_date)->format('d/m/Y') : null,
            'end_time' => substr((string) $s->end_time, 0, 5),
            'crosses_midnight' => ($s->start_date && $s->end_date) ? $s->start_date->ne($s->end_date) : false,
            'total_hours' => $s->total_hours,
            'price' => (float) $s->price,
            'duration_minutes' => $s->durationInMinutes(),
            'status_label' => $s->signatureLabel(),
        ];
    }

    private function weeklyLimitPayload(array $data): array
    {
        $count = FreelancerService::countInWeeklyWindow($data['freelancer_id'], $data['start_date']);
        $freelancer = Freelancer::find($data['freelancer_id']);

        return [
            'error' => 'Limite semanal recomendado excedido',
            'requires_confirmation' => true,
            'requires_pin' => true,
            'weekly_limit' => FreelancerService::WEEKLY_LIMIT,
            'services_in_window' => $count,
            'services_after_save' => $count + 1,
            'message' => 'Com este registro, ' . ($freelancer?->name ?? 'o freelancer') . ' passa a ter '
                . ($count + 1) . ' serviços numa janela de 7 dias (recomendado: '
                . FreelancerService::WEEKLY_LIMIT . ').',
        ];
    }

    /**
     * Decodifica a data URL (image/png) do canvas e grava no disco público.
     * Retorna o caminho relativo salvo.
     */
    private function storeSignatureImage(FreelancerService $service, string $dataUrl): string
    {
        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            throw new \InvalidArgumentException('Formato de assinatura inválido.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            throw new \InvalidArgumentException('Assinatura inválida.');
        }

        $path = 'signatures/service_' . $service->id . '_' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
