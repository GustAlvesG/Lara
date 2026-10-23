<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TournamentException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentRegistration;
use App\Models\Tournament\TournamentTeam;
use App\Models\Tournament\TournamentTeamInvite;
use App\Services\Tournament\TournamentBracketPresenter;
use App\Services\Tournament\TournamentInviteService;
use App\Services\Tournament\TournamentRegistrationService;
use App\Support\Cpf;
use App\Support\ImageSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Torneios pelo site de locação (`/api/tournaments/*`).
 *
 * Consulta (lista e detalhe) só pede o `api_token`. Inscrição, pagamento,
 * "minhas inscrições" e desistência pedem também o `login_token` do sócio
 * (header `Session`): o sócio é sempre o dono do JWT — nenhum endpoint aceita
 * sócio vindo do corpo, e inscrição de outro sócio responde 404.
 *
 * Recusas de regra saem com 4xx e `reason` estável (TournamentException); o
 * site decide a mensagem pelo código.
 *
 * Rascunho e torneio cancelado não aparecem.
 */
class TournamentApiController extends Controller
{
    private const VISIBLE = [Tournament::STATUS_PUBLISHED, Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_FINISHED];

    public function __construct(
        private TournamentRegistrationService $registrations,
        private TournamentInviteService $invites,
        private TournamentBracketPresenter $bracketPresenter,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tournaments = $this->visible()
            ->when($request->filled('place_group_id'), fn ($q) => $q->where('place_group_id', $request->integer('place_group_id')))
            ->orderByDesc('starts_on')
            ->get();

        return response()->json($tournaments->map(fn (Tournament $t) => $this->present($t))->values());
    }

    public function show(int $id): JsonResponse
    {
        $tournament = $this->visible()->find($id);

        if (! $tournament) {
            return $this->notFound();
        }

        return response()->json($this->present($tournament, detail: true));
    }

    /**
     * Chaveamento divulgado de uma categoria: fases, grupos (com a
     * classificação) e jogos. Antes de divulgar: 404 `bracket_not_published`.
     */
    public function bracket(int $id, int $categoryId): JsonResponse
    {
        $tournament = $this->visible()->find($id);
        $category = $tournament?->categories->firstWhere('id', $categoryId);

        if (! $tournament) {
            return $this->notFound();
        }
        if (! $category) {
            return response()->json(['message' => 'Categoria não encontrada.', 'reason' => TournamentException::CATEGORY_NOT_FOUND], 404);
        }
        if (! $category->bracket_published_at) {
            return response()->json(['message' => 'O chaveamento desta categoria ainda não foi divulgado.', 'reason' => TournamentException::BRACKET_NOT_PUBLISHED], 404);
        }

        return response()->json(['tournament_id' => $tournament->id] + $this->bracketPresenter->category($category));
    }

    /** Para cada categoria: pode se inscrever, por que não, e a inscrição que já existe. */
    public function eligibility(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        $tournament = $this->visible()->find($id);

        if (! $member) {
            return $this->noSession();
        }
        if (! $tournament) {
            return $this->notFound();
        }

        try {
            $profile = $this->registrations->profileFor($member);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'tournament_id' => $tournament->id,
            'registration_open' => $tournament->registrationOpen(),
            'categories' => array_map(fn (array $row) => [
                'category_id' => $row['category_id'],
                'eligible' => $row['eligible'],
                'reason' => $row['reason'],
                'registration' => $row['registration'] ? $this->presentRegistration($row['registration']) : null,
            ], $this->registrations->eligibility($tournament, $profile, $member)),
        ]);
    }

    public function register(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        $tournament = $this->visible()->find($id);

        if (! $member) {
            return $this->noSession();
        }
        if (! $tournament) {
            return $this->notFound();
        }

        $data = Validator::make($request->except(['user', 'member_id', 'cpf']), [
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer',
        ])->validate();

        try {
            $profile = $this->registrations->profileFor($member);
            $created = $this->registrations->register($tournament, $member, $profile, $data['category_ids']);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $created->load('category.tournament');

        return response()->json([
            'registrations' => $created->map(fn ($r) => $this->presentRegistration($r))->values(),
            'total_due' => round((float) $created->where('status', TournamentRegistration::STATUS_PENDING_PAYMENT)->sum('amount'), 2),
            'pay_until' => $tournament->registration_ends_at->toIso8601String(),
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return $this->noSession();
        }

        $registrations = TournamentRegistration::with(['category.tournament', 'payment', 'teamMembership.team'])
            ->where('member_id', $member->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($registrations->map(fn ($r) => $this->presentRegistration($r))->values());
    }

    /**
     * Pagamento eRede feito pelo site. Mesmo contrato do
     * `POST /api/schedule/payment`: o mesmo tid reenviado para as mesmas
     * inscrições devolve 200 com o pagamento já gravado.
     */
    public function pay(Request $request): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return $this->noSession();
        }

        $data = Validator::make($request->except(['user', 'member_id', 'cpf']), [
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer',
            'payment_method' => 'required|string|max:40',
            'paid_amount' => 'required|numeric|min:0',
            'payment_integration_id' => 'required|string|max:255',
            'paid_at' => 'nullable|date',
        ])->validate();

        try {
            $result = $this->registrations->pay($member, $data['registration_ids'], $data);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        $payment = $result['payment']->load('registrations.category.tournament');

        return response()->json([
            'message' => $result['created'] ? 'Pagamento registrado.' : 'Pagamento já registrado.',
            'payment' => [
                'id' => $payment->id,
                'paid_amount' => (float) $payment->paid_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'payment_integration_id' => $payment->payment_integration_id,
            ],
            'registrations' => $payment->registrations->map(fn ($r) => $this->presentRegistration($r))->values(),
        ], $result['created'] ? 201 : 200);
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return $this->noSession();
        }

        $registration = TournamentRegistration::where('member_id', $member->id)->find($id);

        if (! $registration) {
            return response()->json(['message' => 'Inscrição não encontrada.', 'reason' => TournamentException::NOT_OWNER], 404);
        }

        try {
            $this->registrations->withdraw($registration, $member);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presentRegistration($registration->fresh(['category.tournament', 'payment'])));
    }

    /** Capitão: cria o time numa categoria montada por capitão (precisa estar inscrito nela). */
    public function createTeam(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $category = TournamentCategory::whereHas('tournament', fn ($q) => $q->whereIn('status', self::VISIBLE))->find($id);
        if (! $category) {
            return response()->json(['message' => 'Categoria não encontrada.', 'reason' => TournamentException::CATEGORY_NOT_FOUND], 404);
        }

        $data = Validator::make($request->only('name'), ['name' => 'nullable|string|max:60'])->validate();

        try {
            $team = $this->invites->createTeam($category, $member, trim((string) ($data['name'] ?? '')));
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presentTeam($team->fresh(), $member), 201);
    }

    /** Os times do sócio, com quem está neles e, para o capitão, os convites pendentes. */
    public function myTeams(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $teams = TournamentTeam::whereHas('registrations', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', '!=', TournamentTeam::STATUS_CANCELLED)
            ->with('category.tournament')
            ->orderByDesc('id')
            ->get();

        return response()->json($teams->map(fn ($team) => $this->presentTeam($team, $member))->values());
    }

    /**
     * Quem convidar: por matrícula (a família do título) ou por CPF. Devolve
     * nome, CPF mascarado e a `ref` para o convite — nunca o CPF.
     */
    public function lookup(Request $request): JsonResponse
    {
        if (! $this->member($request)) {
            return $this->noSession();
        }

        $data = Validator::make($request->query(), [
            'title' => 'required_without:cpf|nullable|string|max:20',
            'cpf' => 'required_without:title|nullable|string|max:20',
        ])->validate();

        return response()->json($this->invites->lookup($data['title'] ?? null, $data['cpf'] ?? null));
    }

    public function invite(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $team = TournamentTeam::find($id);
        if (! $team) {
            return $this->teamNotFound();
        }

        $data = Validator::make($request->only('ref'), ['ref' => 'required|string|max:1000'])->validate();

        try {
            $invite = $this->invites->invite($team, $member, $data['ref']);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->presentInvite($invite->fresh('team.category.tournament')), 201);
    }

    /** Convites pendentes recebidos pelo sócio (pelo CPF dele). */
    public function myInvites(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $invites = TournamentTeamInvite::with(['team.category.tournament', 'team.registrations', 'invitedBy'])
            ->where('cpf', Cpf::digits($member->cpf))
            ->where('status', TournamentTeamInvite::STATUS_PENDING)
            ->orderByDesc('id')
            ->get();

        return response()->json($invites->map(fn ($invite) => $this->presentInvite($invite))->values());
    }

    public function acceptInvite(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $invite = TournamentTeamInvite::find($id);
        if (! $invite) {
            return $this->inviteNotFound();
        }

        try {
            $registration = $this->invites->accept($invite, $member);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'registration' => $this->presentRegistration($registration->load(['category.tournament', 'payment', 'teamMembership.team'])),
            'team' => $this->presentTeam($invite->team->fresh(), $member),
        ]);
    }

    public function declineInvite(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $invite = TournamentTeamInvite::find($id);
        if (! $invite) {
            return $this->inviteNotFound();
        }

        try {
            $this->invites->decline($invite, $member);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['message' => 'Convite recusado.']);
    }

    /** O capitão retira um convite ainda não respondido. */
    public function cancelInvite(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $invite = TournamentTeamInvite::find($id);
        if (! $invite) {
            return $this->inviteNotFound();
        }

        try {
            $this->invites->cancel($invite, $member);
        } catch (TournamentException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json(['message' => 'Convite cancelado.']);
    }

    private function presentTeam(TournamentTeam $team, ?Member $member = null): array
    {
        $team->loadMissing(['registrations', 'captain', 'category.tournament']);
        $isCaptain = $member && $team->captain?->member_id === $member->id;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'status' => $team->status,
            'status_label' => TournamentTeam::STATUS_LABELS[$team->status] ?? $team->status,
            'team_size' => $team->category->team_size,
            'category' => ['id' => $team->category->id, 'name' => $team->category->name, 'team_formation' => $team->category->team_formation],
            'tournament' => ['id' => $team->category->tournament->id, 'title' => $team->category->tournament->title],
            'is_captain' => $isCaptain,
            'members' => $team->registrations->map(fn ($r) => [
                'name' => $r->name,
                'paid' => $r->status === TournamentRegistration::STATUS_PAID,
                'captain' => $team->captain_registration_id === $r->id,
            ])->values(),
            'pending_invites' => $isCaptain
                ? $team->invites()->where('status', TournamentTeamInvite::STATUS_PENDING)->get()->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()
                : [],
        ];
    }

    private function presentInvite(TournamentTeamInvite $invite): array
    {
        $team = $invite->team;
        $category = $team->category;

        return [
            'id' => $invite->id,
            'status' => $invite->status,
            'name' => $invite->name,
            'invited_by' => $invite->invitedBy?->name,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->registrations->pluck('name')->values(),
            ],
            'category' => ['id' => $category->id, 'name' => $category->name, 'price' => (float) $category->price],
            'tournament' => [
                'id' => $category->tournament->id,
                'title' => $category->tournament->title,
                'registration_ends_at' => $category->tournament->registration_ends_at->toIso8601String(),
            ],
        ];
    }

    private function teamNotFound(): JsonResponse
    {
        return response()->json(['message' => 'Time não encontrado.', 'reason' => TournamentException::TEAM_NOT_FOUND], 404);
    }

    private function inviteNotFound(): JsonResponse
    {
        return response()->json(['message' => 'Convite não encontrado.', 'reason' => TournamentException::INVITE_NOT_FOUND], 404);
    }

    private function visible()
    {
        return Tournament::with(['placeGroup', 'categories' => fn ($q) => $q->withCount([
            'registrations as paid_count' => fn ($r) => $r->where('status', TournamentRegistration::STATUS_PAID),
        ])])->whereIn('status', self::VISIBLE);
    }

    private function present(Tournament $tournament, bool $detail = false): array
    {
        $data = [
            'id' => $tournament->id,
            'title' => $tournament->title,
            'description' => $tournament->description,
            'image_url' => ImageSource::url($tournament->image),
            'place_group' => $tournament->placeGroup ? ['id' => $tournament->placeGroup->id, 'name' => $tournament->placeGroup->name] : null,
            'status' => $tournament->status,
            'registration_starts_at' => $tournament->registration_starts_at->toIso8601String(),
            'registration_ends_at' => $tournament->registration_ends_at->toIso8601String(),
            'registration_open' => $tournament->registrationOpen(),
            'starts_on' => $tournament->starts_on->toDateString(),
            'ends_on' => $tournament->ends_on->toDateString(),
            'categories' => $tournament->categories->map(fn (TournamentCategory $c) => $this->presentCategory($c))->values(),
        ];

        if ($detail) {
            $data['rules'] = $tournament->rules;

            // Os times formados de cada categoria (nome e quem joga).
            $teams = TournamentTeam::with('registrations')
                ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
                ->where('status', '!=', TournamentTeam::STATUS_CANCELLED)
                ->orderBy('name')
                ->get()
                ->groupBy('tournament_category_id');

            $data['categories'] = $data['categories']->map(fn (array $category) => $category + [
                'teams' => ($teams[$category['id']] ?? collect())->map(fn (TournamentTeam $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'status' => $t->status,
                    'members' => $t->registrations->pluck('name')->values(),
                ])->values(),
            ]);
        }

        return $data;
    }

    private function presentCategory(TournamentCategory $category): array
    {
        $paid = (int) ($category->paid_count ?? 0);

        return [
            'id' => $category->id,
            'name' => $category->name,
            'summary' => TournamentCategory::describe($category->team_size, $category->gender, $category->min_age, $category->max_age),
            'team_size' => $category->team_size,
            'gender' => $category->gender,
            'min_age' => $category->min_age,
            'max_age' => $category->max_age,
            'price' => (float) $category->price,
            'max_registrations' => $category->max_registrations,
            'paid_registrations' => $paid,
            'vacancies_left' => $category->max_registrations !== null ? max(0, $category->max_registrations - $paid) : null,
            'team_formation' => $category->team_formation,
            'format' => $category->format,
            'sets_to_win' => $category->sets_to_win,
            'bracket_published' => $category->bracket_published_at !== null,
        ];
    }

    private function presentRegistration(TournamentRegistration $registration): array
    {
        $category = $registration->category;

        return [
            'id' => $registration->id,
            'status' => $registration->status,
            'status_label' => TournamentRegistration::STATUS_LABELS[$registration->status] ?? $registration->status,
            'amount' => (float) $registration->amount,
            'paid_at' => $registration->paid_at?->toIso8601String(),
            'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
            'tournament' => $category?->tournament ? [
                'id' => $category->tournament->id,
                'title' => $category->tournament->title,
                'starts_on' => $category->tournament->starts_on->toDateString(),
                'registration_ends_at' => $category->tournament->registration_ends_at->toIso8601String(),
            ] : null,
            'payment' => $registration->relationLoaded('payment') && $registration->payment ? [
                'id' => $registration->payment->id,
                'manual' => (bool) $registration->payment->is_manual,
                'status' => $registration->payment->status,
            ] : null,
            'team' => $registration->relationLoaded('teamMembership') && $registration->teamMembership?->team ? [
                'id' => $registration->teamMembership->team->id,
                'name' => $registration->teamMembership->team->name,
                'status' => $registration->teamMembership->team->status,
            ] : null,
        ];
    }

    /** O sócio do JWT, pelo mesmo critério da API dos espaços sociais. */
    private function member(Request $request): ?Member
    {
        $cpf = data_get($request->input('user'), 'username');

        return $cpf ? Member::where('cpf', $cpf)->first() : null;
    }

    private function noSession(): JsonResponse
    {
        return response()->json(['message' => 'Sessão inválida.'], 401);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Torneio não encontrado.', 'reason' => TournamentException::TOURNAMENT_NOT_FOUND], 404);
    }
}
