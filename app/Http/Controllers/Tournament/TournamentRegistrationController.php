<?php

namespace App\Http\Controllers\Tournament;

use App\Authorization\Permissions as P;
use App\Exceptions\TournamentException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SchedulePayment;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentRegistration;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\Tournament\TournamentRegistrationService;
use App\Support\Cpf;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Inscrições de um torneio no painel (permissão `torneios.inscricoes`):
 * lista com busca e filtros, inscrição pelo balcão, cancelamento (estorno
 * pede também `torneios.pagamentos.estornar`) e baixa manual
 * (`torneios.pagamentos.baixa-manual`). Regras em
 * TournamentRegistrationService.
 */
class TournamentRegistrationController extends Controller
{
    public function __construct(
        private TournamentRegistrationService $registrations,
        private MemberProfileLookup $profiles,
    ) {
    }

    public function index(Request $request, Tournament $tournament): View
    {
        $tournament->load(['categories', 'placeGroup']);
        $term = trim((string) $request->input('q'));

        $registrations = TournamentRegistration::with(['category', 'payment'])
            ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->when($request->filled('category'), fn ($q) => $q->where('tournament_category_id', $request->integer('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($term !== '', function ($q) use ($term) {
                $digits = Cpf::digits($term);
                $q->where(function ($q) use ($term, $digits) {
                    $q->where('name', 'like', "%{$term}%")->orWhere('title', $term);
                    if ($digits !== '') {
                        $q->orWhere('cpf', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('tournaments.registrations', [
            'tournament' => $tournament,
            'registrations' => $registrations,
        ]);
    }

    /**
     * Inscrição pelo balcão: a pessoa precisa ter cadastro no site de locação
     * (o sócio local) e ser sócia de título ativo no MultiClubes. Quem o sexo
     * ou a idade recusariam entra com justificativa.
     */
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'cpf' => 'required|string|max:20',
            'title' => 'required|string|max:20',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer',
            'override_reason' => 'nullable|string|max:500',
        ], [
            'category_ids.required' => 'Escolha ao menos uma categoria.',
        ], ['cpf' => 'CPF', 'title' => 'matrícula']);

        $cpf = Cpf::digits($data['cpf']);
        $profile = $this->profiles->find($cpf, $data['title']);

        if (! $profile) {
            return back()->withInput()->with('error', 'Não há sócio de título ativo no MultiClubes com esse CPF nessa matrícula.');
        }

        $member = Member::where('cpf', $cpf)->where('title', $profile->title)->first() ?? Member::where('cpf', $cpf)->first();

        if (! $member) {
            return back()->withInput()->with('error', "{$profile->name} ainda não tem cadastro no site de locação. Peça para a pessoa se cadastrar no site e tente de novo.");
        }

        $override = filled($data['override_reason'] ?? null) ? [$request->user(), trim($data['override_reason'])] : null;

        try {
            $created = $this->registrations->register($tournament, $member, $profile, $data['category_ids'], $override);
        } catch (TournamentException $e) {
            $hint = $e->reason === TournamentException::INELIGIBLE ? ' Para liberar mesmo assim, preencha a justificativa.' : '';

            return back()->withInput()->with('error', $e->getMessage() . $hint);
        }

        return redirect()->route('tournaments.registrations.index', $tournament)
            ->with('success', "Inscrição de {$profile->name} feita em " . $created->count() . ' categoria(s).');
    }

    public function cancel(Request $request, TournamentRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => 'required|string|max:500',
            'refund' => 'nullable|boolean',
        ], ['cancel_reason.required' => 'Informe o motivo do cancelamento.']);

        $refund = $request->boolean('refund');

        if ($refund && ! $request->user()->can(P::TORNEIOS_PAGAMENTOS_ESTORNAR)) {
            abort(403);
        }

        $tournament = $registration->category->tournament;

        try {
            $refunded = $this->registrations->cancel($registration, $request->user(), $data['cancel_reason'], $refund);
        } catch (TournamentException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (RequestException $e) {
            return back()->with('error', 'O estorno não funcionou e a inscrição continua ativa. Tire um print desta mensagem e envie para a TI. Detalhe: ' . $e->getMessage());
        }

        $message = "Inscrição de {$registration->name} cancelada"
            . ($refunded > 0 ? ', com estorno de R$ ' . number_format($refunded, 2, ',', '.') : '') . '.';

        return redirect()->route('tournaments.registrations.index', $tournament)->with('success', $message);
    }

    public function manual(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer',
            'payment_method' => ['required', Rule::in(array_keys(SchedulePayment::MANUAL_METHODS))],
            'paid_at' => 'nullable|date|before_or_equal:now',
            'manual_notes' => 'nullable|string|max:500',
        ], [
            'registration_ids.required' => 'Escolha a inscrição a dar baixa.',
            'payment_method.required' => 'Escolha a forma em que o pagamento foi recebido.',
            'paid_at.before_or_equal' => 'A data do pagamento não pode estar no futuro.',
        ]);

        $belongs = TournamentRegistration::whereIn('id', $data['registration_ids'])
            ->whereIn('tournament_category_id', $tournament->categories()->pluck('id'))
            ->count();
        abort_unless($belongs === count(array_unique($data['registration_ids'])), 404);

        try {
            $payment = $this->registrations->recordManual($data['registration_ids'], $data, $request->user());
        } catch (TournamentException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tournaments.registrations.index', $tournament)
            ->with('success', 'Baixa registrada: R$ ' . number_format((float) $payment->paid_amount, 2, ',', '.') . '.');
    }
}
