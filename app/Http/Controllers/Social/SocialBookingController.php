<?php

namespace App\Http\Controllers\Social;

use App\Authorization\Permissions as P;
use App\Exceptions\SocialBookingException;
use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Services\MemberService;
use App\Services\Social\SocialAvailabilityService;
use App\Services\Social\SocialBookingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDF;

/**
 * Painel das reservas de espaço social (Reservas › Espaços Sociais).
 *
 * `reservas.sociais` vê, pré-reserva no balcão e cuida da lista de
 * convidados; `reservas.sociais.aprovar` decide — contrato, aprovação com
 * desconto, recusa e cancelamento. Estornar no cancelamento pede também
 * `reservas.pagamentos.estornar`, a mesma porta do estorno das quadras.
 *
 * As regras estão em SocialBookingService; aqui é tradução de formulário e
 * mensagem de tela.
 */
class SocialBookingController extends Controller
{
    /** Disco privado: contrato não é imagem de vitrine, não vai para public/. */
    private const CONTRACT_DIR = 'social-contracts';

    public function __construct(
        private SocialBookingService $bookings,
        private SocialAvailabilityService $availability,
    ) {
    }

    public function index(Request $request): View
    {
        $query = SocialBooking::with(['place.group', 'member'])->latest('id');

        if ($request->filled('stage')) {
            $query->where('stage', $request->input('stage'));
        }
        if ($request->filled('place_id')) {
            $query->where('place_id', $request->input('place_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('event_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('event_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('member')) {
            $term = $request->input('member');
            $query->whereHas('member', fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('cpf', 'like', '%' . preg_replace('/\D/', '', $term) . '%'));
        }

        return view('location.social.index', [
            'bookings' => $query->paginate(25)->withQueryString(),
            'places' => $this->socialPlaces(),
            'stages' => SocialBooking::STAGE_LABELS,
            'counts' => SocialBooking::selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage'),
        ]);
    }

    public function create(): View
    {
        return view('location.social.create', [
            'places' => $this->socialPlaces(activeOnly: true),
            'beverages' => SocialBeverage::active()->orderBy('name')->get(),
        ]);
    }

    /** Pré-reserva lançada no balcão em nome do sócio. */
    public function store(Request $request, MemberService $members): RedirectResponse
    {
        $data = $request->validate([
            'place_id' => 'required|integer|exists:places,id',
            'date' => 'required|date_format:Y-m-d',
            'event_type' => 'required|string|max:120',
            'expected_guests' => 'nullable|integer|min:1|max:10000',
            'extra_guests' => 'nullable|integer|min:0|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.beverage_id' => 'required_with:items|integer',
            'items.*.quantity' => 'nullable|integer|min:0|max:999',
            'guests' => 'nullable|array',
            'guests.*.name' => 'nullable|string|max:255',
            'guests.*.cpf' => 'nullable|string|max:20',
            'cpf' => 'required|string',
            'title' => 'nullable|string',
            'birthDate' => 'nullable|string',
        ], [
            'cpf.required' => 'Escolha o sócio.',
        ]);

        try {
            $memberId = $members->memberByCpf(new Request($request->only(['cpf', 'title', 'birthDate'])));
        } catch (\Throwable $e) {
            $memberId = null;
        }

        if (! is_numeric($memberId)) {
            return back()->withInput()->with('error', 'Não foi possível identificar o sócio no MultiClubes.');
        }

        $data['guests'] = $this->filledGuests($data['guests'] ?? []);

        try {
            $booking = $this->bookings->request(Place::with('group')->findOrFail($data['place_id']), (int) $memberId, $data, $request->user()->id);
        } catch (SocialBookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('social-booking.show', $booking)->with('success', 'Pré-reserva registrada. Falta o contrato e a aprovação.');
    }

    public function show(SocialBooking $booking): View
    {
        $booking->load(['place.group', 'member', 'items', 'guests.oneOffAccess', 'schedule.schedulePayment', 'approver', 'contractChecker', 'refuser', 'canceller', 'creator']);

        return view('location.social.show', [
            'booking' => $booking,
            'beverages' => SocialBeverage::active()->orderBy('name')->get(),
        ]);
    }

    public function updateDetails(Request $request, SocialBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'event_type' => 'required|string|max:120',
            'expected_guests' => 'nullable|integer|min:1|max:10000',
            'extra_guests' => 'nullable|integer|min:0|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.beverage_id' => 'required_with:items|integer',
            'items.*.quantity' => 'nullable|integer|min:0|max:999',
        ]);

        // O formulário sempre manda as bebidas; sem nenhuma marcada, a lista fica vazia.
        $data['items'] = $data['items'] ?? [];
        $data['notes'] = $data['notes'] ?? null;
        $data['expected_guests'] = $data['expected_guests'] ?? null;

        return $this->attempt(fn () => $this->bookings->updateDetails($booking, $data, $request->user()->id), $booking, 'Dados da reserva atualizados.');
    }

    public function contract(Request $request, SocialBooking $booking): RedirectResponse
    {
        $request->validate([
            'contract_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = null;
        if ($request->hasFile('contract_file')) {
            $upload = $request->file('contract_file');
            $file = $upload->storeAs(
                self::CONTRACT_DIR,
                'reserva-' . $booking->id . '-' . Str::random(8) . '.' . $upload->extension(),
                'local'
            );
        }

        return $this->attempt(fn () => $this->bookings->markContract($booking, $request->user()->id, $file), $booking, 'Contrato registrado.');
    }

    public function downloadContract(SocialBooking $booking)
    {
        abort_unless($booking->contract_file && Storage::disk('local')->exists($booking->contract_file), 404);

        return Storage::disk('local')->download($booking->contract_file, 'contrato-reserva-' . $booking->id . '.' . pathinfo($booking->contract_file, PATHINFO_EXTENSION));
    }

    public function approve(Request $request, SocialBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'discount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
        ]);

        return $this->attempt(
            fn () => $this->bookings->approve($booking, (float) ($data['discount'] ?? 0), $data['discount_reason'] ?? null, $request->user()->id),
            $booking,
            'Reserva aprovada. O pagamento foi liberado para o sócio.'
        );
    }

    public function refuse(Request $request, SocialBooking $booking): RedirectResponse
    {
        $data = $request->validate(['refusal_reason' => 'required|string|max:1000'], [
            'refusal_reason.required' => 'Informe o motivo da recusa.',
        ]);

        return $this->attempt(fn () => $this->bookings->refuse($booking, $data['refusal_reason'], $request->user()->id), $booking, 'Pré-reserva recusada.');
    }

    public function cancel(Request $request, SocialBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => 'required|string|max:1000',
            'refund_amount' => 'nullable|numeric|min:0',
        ], [
            'cancel_reason.required' => 'Informe o motivo do cancelamento.',
        ]);

        $refund = (float) ($data['refund_amount'] ?? 0);

        // Checado aqui, antes: daqui para baixo, toda recusa é do estorno — o
        // cancelamento em si já ficou gravado.
        if (! $booking->isActive()) {
            return redirect()->route('social-booking.show', $booking)->with('error', 'Esta reserva já está encerrada.');
        }

        if ($refund > 0 && ! $request->user()->can(P::RESERVAS_PAGAMENTOS_ESTORNAR)) {
            return back()->withInput()->with('error', 'Você não tem permissão para estornar pagamentos. Cancele sem estorno ou peça a quem tem.');
        }

        try {
            $result = $this->bookings->cancel($booking, $data['cancel_reason'], $request->user()->id, $refund > 0 ? $refund : null);
        } catch (SocialBookingException|\InvalidArgumentException $e) {
            return redirect()->route('social-booking.show', $booking)->with('error', 'Reserva cancelada, mas o estorno não foi feito: ' . $e->getMessage());
        } catch (RequestException $e) {
            return redirect()->route('social-booking.show', $booking)->with('error', 'Reserva cancelada, mas o estorno não funcionou na Rede. Tire um print desta mensagem e envie para a TI. Detalhe: ' . $e->getMessage());
        }

        $message = $result['refunded'] > 0
            ? 'Reserva cancelada e R$ ' . number_format($result['refunded'], 2, ',', '.') . ' estornados.'
            : 'Reserva cancelada.';

        return redirect()->route('social-booking.show', $booking)->with('success', $message);
    }

    public function updateGuests(Request $request, SocialBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'guests' => 'nullable|array',
            'guests.*.name' => 'nullable|string|max:255',
            'guests.*.cpf' => 'nullable|string|max:20',
        ]);

        return $this->attempt(
            fn () => $this->bookings->replaceGuests($booking, $this->filledGuests($data['guests'] ?? []), false, $request->user()->id),
            $booking,
            'Lista de convidados atualizada.'
        );
    }

    /** Lista para a portaria (impressa), com CPF completo: é o documento que confere. */
    public function guestsPdf(SocialBooking $booking)
    {
        $booking->load(['place.group', 'member', 'guests']);

        return PDF::loadView('location.social.guests-pdf', ['booking' => $booking])
            ->stream('convidados-reserva-' . $booking->id . '.pdf');
    }

    /** Calendário do mês para o formulário de pré-reserva do painel. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'place_id' => 'required|integer',
            'month' => 'required|date_format:Y-m',
        ]);

        $place = Place::with('group.weekdays')->find($data['place_id']);

        if (! $place || ! $place->isSocial()) {
            return response()->json(['message' => 'Espaço social não encontrado.'], 404);
        }

        return response()->json([
            'place_id' => $place->id,
            'month' => $data['month'],
            'days' => $this->availability->month($place, $data['month']),
        ]);
    }

    private function attempt(callable $action, SocialBooking $booking, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (SocialBookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('social-booking.show', $booking)->with('success', $success);
    }

    /** Linhas em branco do formulário (o "adicionar convidado" deixa sobras) não são convidados. */
    private function filledGuests(array $guests): array
    {
        return array_values(array_filter($guests, fn ($g) => trim((string) ($g['name'] ?? '')) !== '' || trim((string) ($g['cpf'] ?? '')) !== ''));
    }

    private function socialPlaces(bool $activeOnly = false)
    {
        return Place::with('group')
            ->whereHas('group', fn ($q) => $q->social())
            ->when($activeOnly, fn ($q) => $q->where('status_id', 1))
            ->get();
    }
}
