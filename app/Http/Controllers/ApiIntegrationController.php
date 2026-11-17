<?php

namespace App\Http\Controllers;

use App\Models\ApiTokens\ApiIntegration;
use App\Models\ApiTokens\ApiIntegrationToken;
use App\Services\ApiTokens\ApiTokenGuard;
use App\Services\ApiTokens\ApiTokenManager;
use App\Support\ApiTokens\ApiScopes;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Administração › Integrações: os sistemas externos que chamam a API do
 * Lara, os tokens de cada instalação (gerar, rotacionar, revogar) e o último
 * uso de cada token. Ver docs/funcionalidades/gestao-tokens.md.
 *
 * Ver: `acessar-integracoes` (visualizar ou gerenciar). Escrever:
 * `integracoes.gerenciar` (na rota).
 */
class ApiIntegrationController extends Controller
{
    /** Opções de validade da tela, em dias. */
    public const VALIDITY_DAYS = [30, 90, 180, 365];

    /** Escopo sugerido ao gerar token, pelo sistema da integração. */
    private const SUGGESTED_SCOPES = [
        'nextjs' => [ApiScopes::SITE],
        'ia' => [ApiScopes::IA],
        'portaria' => [ApiScopes::PORTARIA],
        'home-assistant' => [ApiScopes::HOME_ASSISTANT],
        'telegram' => [ApiScopes::TELEGRAM],
        'poli' => [ApiScopes::WEBHOOK_WHATSAPP],
    ];

    public function __construct(private ApiTokenManager $tokens)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));

        $integrations = ApiIntegration::query()
            ->with('tokens')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('tokens', fn ($t) => $t->where('name', 'like', $like)));
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        return view('api-integrations.index', [
            'integrations' => $integrations,
            'search' => $search,
            'unmanaged' => $this->unmanagedUsage(),
            'legacyEnabled' => (bool) config('api_tokens.legacy_enabled', true),
            'portariaEnforced' => (bool) config('api_tokens.portaria_enforced', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:api_integrations,name',
            'system' => ['required', Rule::in(array_keys(ApiIntegration::SYSTEMS))],
            'notes' => 'nullable|string|max:2000',
        ], [], ['name' => 'nome', 'system' => 'sistema', 'notes' => 'observação']);

        $integration = ApiIntegration::create($data + ['active' => true, 'created_by' => $request->user()?->id]);

        return redirect()->route('api-integrations.show', $integration)
            ->with('success', 'Integração cadastrada. Gere agora um token para cada instalação.');
    }

    public function show(ApiIntegration $integration): View
    {
        $integration->load('tokens');

        $hits = DB::table('api_token_usage_daily')
            ->whereIn('api_integration_token_id', $integration->tokens->pluck('id'))
            ->where('kind', ApiTokenGuard::KIND_TOKEN)
            ->where('day', '>=', now()->subDays(6)->toDateString())
            ->groupBy('api_integration_token_id')
            ->pluck(DB::raw('SUM(hits)'), 'api_integration_token_id');

        $rejected = DB::table('api_token_usage_daily')
            ->whereIn('api_integration_token_id', $integration->tokens->pluck('id'))
            ->where('kind', ApiTokenGuard::KIND_REJECTED)
            ->where('day', '>=', now()->subDays(6)->toDateString())
            ->groupBy('api_integration_token_id')
            ->pluck(DB::raw('SUM(hits)'), 'api_integration_token_id');

        return view('api-integrations.show', [
            'integration' => $integration,
            'hits' => $hits,
            'rejected' => $rejected,
            'suggestedScopes' => self::SUGGESTED_SCOPES[$integration->system] ?? [],
            'graceDays' => (int) config('api_tokens.rotation_grace_days', 7),
        ]);
    }

    public function update(Request $request, ApiIntegration $integration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('api_integrations', 'name')->ignore($integration->id)],
            'system' => ['required', Rule::in(array_keys(ApiIntegration::SYSTEMS))],
            'notes' => 'nullable|string|max:2000',
            'active' => 'nullable|boolean',
        ], [], ['name' => 'nome', 'system' => 'sistema', 'notes' => 'observação']);

        $integration->update([
            'name' => $data['name'],
            'system' => $data['system'],
            'notes' => $data['notes'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', $integration->active
            ? 'Integração salva.'
            : 'Integração salva e desativada: nenhum token dela passa até reativar.');
    }

    public function generate(Request $request, ApiIntegration $integration): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'scopes' => 'required|array|min:1',
            'scopes.*' => ['string', Rule::in(ApiScopes::all())],
        ], [
            'scopes.required' => 'Escolha pelo menos um escopo.',
        ], ['name' => 'instalação', 'scopes' => 'escopos']);

        $expiresAt = $this->expiresAt($request);

        try {
            $result = $this->tokens->generate($integration, $data['name'], $data['scopes'], $expiresAt, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return $this->withPlainToken($integration, $result, 'Token gerado.');
    }

    public function rotate(Request $request, ApiIntegrationToken $token): RedirectResponse
    {
        $data = $request->validate([
            'grace_days' => 'required|integer|min:0|max:90',
        ], [], ['grace_days' => 'dias de convivência']);

        $expiresAt = $this->expiresAt($request);

        try {
            $result = $this->tokens->rotate($token, $expiresAt, (int) $data['grace_days'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['validade' => $e->getMessage()]);
        }

        $message = (int) $data['grace_days'] > 0
            ? "Token novo gerado. O antigo continua valendo até {$token->fresh()->expires_at?->format('d/m/Y H:i')}: troque no sistema antes disso."
            : 'Token novo gerado e o antigo revogado.';

        return $this->withPlainToken($token->integration, $result, $message);
    }

    public function revoke(Request $request, ApiIntegrationToken $token): RedirectResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:250'], [], ['reason' => 'motivo']);

        $this->tokens->revoke($token, $request->user(), $data['reason'] ?? null);

        return redirect()->route('api-integrations.show', $token->api_integration_id)
            ->with('success', "Token \"{$token->name}\" revogado: a próxima chamada com ele recebe 401.");
    }

    /** O texto do token vai uma vez só, pela sessão flash, e nunca para o log. */
    private function withPlainToken(ApiIntegration $integration, array $result, string $message): RedirectResponse
    {
        return redirect()->route('api-integrations.show', $integration)
            ->with('success', $message)
            ->with('new_api_token', ['id' => $result['token']->id, 'name' => $result['token']->name, 'plain' => $result['plain']]);
    }

    private function expiresAt(Request $request): ?CarbonInterface
    {
        $options = array_merge(array_map('strval', self::VALIDITY_DAYS), ['data', 'sem']);

        $data = $request->validate([
            'validade' => ['required', Rule::in($options)],
            'validade_data' => 'required_if:validade,data|nullable|date|after:today',
        ], [
            'validade.required' => 'Escolha a validade do token.',
            'validade_data.required_if' => 'Informe a data de validade.',
            'validade_data.after' => 'A validade precisa ser uma data futura.',
        ], ['validade' => 'validade', 'validade_data' => 'data de validade']);

        return match ($data['validade']) {
            'sem' => null,
            'data' => Carbon::parse($data['validade_data'])->endOfDay(),
            default => now()->addDays((int) $data['validade']),
        };
    }

    /**
     * Chamadas sem token gerenciado nos últimos 30 dias: o API_TOKEN antigo
     * ("legado") e o Monitor de Acesso sem token ("sem token"), por IP e
     * escopo. É a lista que precisa zerar antes de desligar o token antigo.
     */
    private function unmanagedUsage(): Collection
    {
        return DB::table('api_token_usage_daily')
            ->whereIn('kind', [ApiTokenGuard::KIND_LEGACY, ApiTokenGuard::KIND_MISSING])
            ->where('day', '>=', now()->subDays(29)->toDateString())
            ->groupBy('kind', 'scope', 'ip')
            ->select('kind', 'scope', 'ip', DB::raw('SUM(hits) as hits'), DB::raw('MAX(last_seen_at) as last_seen_at'), DB::raw('MAX(last_route) as last_route'))
            ->orderByDesc('last_seen_at')
            ->get();
    }
}
