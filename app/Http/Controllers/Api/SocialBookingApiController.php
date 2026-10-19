<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SocialBookingException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Services\Social\SocialAvailabilityService;
use App\Services\Social\SocialBookingService;
use App\Support\ImageSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Reserva de espaço social pelo app de locação (`/api/social/*`).
 *
 * Autenticação dupla, como o resto do app: `api_token` (a aplicação) e
 * `login_token` (o sócio, header `Session`). O sócio é sempre o dono do JWT —
 * nenhum endpoint aceita sócio vindo do corpo, e reserva de outro sócio
 * responde 404, sem confirmar que existe.
 *
 * Recusas de regra saem com 422/409 e `reason` estável
 * (SocialBookingException); o app decide a mensagem pelo código.
 *
 * O pagamento não é daqui: é o `POST /api/schedule/payment` de sempre, com o
 * `schedule_id` da reserva e o `total` exato.
 */
class SocialBookingApiController extends Controller
{
    public function __construct(
        private SocialBookingService $bookings,
        private SocialAvailabilityService $availability,
    ) {
    }

    /** Grupos sociais (Churrasqueiras, Salões…), com as regras do clube. */
    public function groups(): JsonResponse
    {
        $groups = PlaceGroup::social()
            ->withCount(['places' => fn ($q) => $q->where('status_id', 1)])
            ->orderBy('name')
            ->get()
            ->map(fn (PlaceGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'image_vertical' => $this->imageUrl($group->image_vertical),
                'image_horizontal' => $this->imageUrl($group->image_horizontal),
                'rules' => $group->social_rules,
                'minimum_antecedence' => (int) $group->minimum_antecedence,
                'maximum_antecedence' => (int) $group->maximum_antecedence,
                'places_count' => $group->places_count,
            ]);

        return response()->json($groups);
    }

    /** Espaços ativos de um grupo social. */
    public function places(int $group): JsonResponse
    {
        $group = PlaceGroup::social()->find($group);

        if (! $group) {
            return response()->json(['message' => 'Grupo não encontrado.', 'reason' => SocialBookingException::PLACE_NOT_SOCIAL], 404);
        }

        $places = $group->places()->where('status_id', 1)->get()->map(fn (Place $place) => $this->presentPlace($place));

        return response()->json(['group' => ['id' => $group->id, 'name' => $group->name, 'rules' => $group->social_rules], 'places' => $places]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'place_id' => 'required|integer',
            'month' => 'required|date_format:Y-m',
        ])->validate();

        $place = $this->socialPlace((int) $data['place_id']);

        if (! $place) {
            return $this->placeNotFound();
        }

        return response()->json([
            'place_id' => $place->id,
            'month' => $data['month'],
            'price' => (float) $place->price,
            'days' => $this->availability->month($place, $data['month']),
        ]);
    }

    /** Bebidas ativas (só a categoria bebida, como antes do mobiliário). */
    public function beverages(): JsonResponse
    {
        return response()->json(
            SocialBeverage::active()->beverages()->orderBy('name')->get()->map(fn (SocialBeverage $b) => $this->presentCatalogItem($b))
        );
    }

    /** Mesas, cadeiras e jogos completos ativos, com o que cada unidade acrescenta. */
    public function furniture(): JsonResponse
    {
        return response()->json(
            SocialBeverage::active()->furniture()->orderBy('name')->get()->map(fn (SocialBeverage $b) => $this->presentCatalogItem($b))
        );
    }

    private function presentCatalogItem(SocialBeverage $item): array
    {
        return [
            'id' => $item->id,
            'category' => $item->category,
            'name' => $item->name,
            'unit' => $item->unit,
            'price' => (float) $item->price,
            'tables_per_unit' => (int) $item->tables_per_unit,
            'chairs_per_unit' => (int) $item->chairs_per_unit,
            'max_quantity' => $item->max_quantity,
        ];
    }

    public function limits(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $group = PlaceGroup::social()->find((int) $request->query('place_group_id'));
        if (! $group) {
            return response()->json(['message' => 'Grupo não encontrado.', 'reason' => SocialBookingException::PLACE_NOT_SOCIAL], 404);
        }

        return response()->json($this->availability->limits($group, $member->id));
    }

    public function index(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $today = Carbon::today()->toDateString();

        $bookings = SocialBooking::with(['place.group', 'items', 'guests'])
            ->where('member_id', $member->id)
            ->get()
            // Futuras primeiro (da mais próxima), depois as passadas (da mais recente).
            ->sortBy(fn (SocialBooking $b) => [$b->event_date->toDateString() >= $today ? 0 : 1, $b->event_date->toDateString() >= $today ? $b->event_date->timestamp : -$b->event_date->timestamp])
            ->values()
            ->map(fn (SocialBooking $b) => $this->present($b));

        return response()->json($bookings);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        [$booking, $error] = $this->owned($request, $id);

        return $error ?? response()->json($this->present($booking));
    }

    public function store(Request $request): JsonResponse
    {
        $member = $this->member($request);
        if (! $member) {
            return $this->noSession();
        }

        $data = Validator::make($request->except(['user', 'member_id', 'cpf']), [
            'place_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'event_type' => 'required|string|max:120',
            'expected_guests' => 'nullable|integer|min:1|max:10000',
            'extra_guests' => 'nullable|integer|min:0|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array|max:50',
            'items.*.item_id' => 'required_without:items.*.beverage_id|integer',
            'items.*.beverage_id' => 'required_without:items.*.item_id|integer',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'guests' => 'nullable|array|max:500',
            'guests.*.name' => 'required|string|max:255',
            'guests.*.cpf' => 'required|string|max:20',
        ])->validate();

        $place = $this->socialPlace((int) $data['place_id']);
        if (! $place) {
            return $this->placeNotFound();
        }

        try {
            $booking = $this->bookings->request($place, $member->id, $data);
        } catch (SocialBookingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->present($booking), 201);
    }

    public function updateGuests(Request $request, int $id): JsonResponse
    {
        [$booking, $error] = $this->owned($request, $id);
        if ($error) {
            return $error;
        }

        $data = Validator::make($request->only('guests'), [
            'guests' => 'present|array|max:500',
            'guests.*.name' => 'required|string|max:255',
            'guests.*.cpf' => 'required|string|max:20',
        ])->validate();

        try {
            $booking = $this->bookings->replaceGuests($booking, $data['guests'], true);
        } catch (SocialBookingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->present($booking->fresh(['place.group', 'items', 'guests'])));
    }

    /**
     * Edita a pré-reserva em análise, antes do contrato ok (depois dele:
     * `contract_closed`): tudo menos data e espaço. Campo ausente
     * fica como está; `items` e `guests`, quando vêm, substituem a lista.
     * `date`/`place_id` no corpo são ignorados (não fazem parte da edição).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$booking, $error] = $this->owned($request, $id);
        if ($error) {
            return $error;
        }

        $data = Validator::make($request->only(['start_time', 'event_type', 'expected_guests', 'extra_guests', 'notes', 'items', 'guests']), [
            'start_time' => 'sometimes|nullable|date_format:H:i',
            'event_type' => 'sometimes|required|string|max:120',
            'expected_guests' => 'sometimes|nullable|integer|min:1|max:10000',
            'extra_guests' => 'sometimes|nullable|integer|min:0|max:1000',
            'notes' => 'sometimes|nullable|string|max:2000',
            'items' => 'sometimes|present|array|max:50',
            'items.*.item_id' => 'required_without:items.*.beverage_id|integer',
            'items.*.beverage_id' => 'required_without:items.*.item_id|integer',
            'items.*.quantity' => 'required|integer|min:0|max:999',
            'guests' => 'sometimes|present|array|max:500',
            'guests.*.name' => 'required|string|max:255',
            'guests.*.cpf' => 'required|string|max:20',
        ])->validate();

        try {
            $booking = $this->bookings->updateDetails($booking, $data);
        } catch (SocialBookingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->present($booking));
    }

    /** Desistência do sócio — só antes de pagar. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        [$booking, $error] = $this->owned($request, $id);
        if ($error) {
            return $error;
        }

        try {
            $booking = $this->bookings->cancelByMember($booking);
        } catch (SocialBookingException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json($this->present($booking->fresh(['place.group', 'items', 'guests'])));
    }

    /** Formato da reserva para o app (contrato descrito em docs/reservas-sociais-prompt-site-locacao.md). */
    private function present(SocialBooking $booking): array
    {
        $booking->loadMissing(['place.group', 'items', 'guests', 'schedule.schedulePayment']);
        $now = Carbon::now();
        $payment = $booking->schedule?->schedulePayment;

        return [
            'id' => $booking->id,
            'schedule_id' => $booking->schedule_id,
            'stage' => $booking->stage,
            'stage_label' => $booking->stageLabel(),
            // Feita pelo sócio no site ou pela secretaria no balcão (em nome
            // dele). A do balcão pode já chegar aprovada, para pagar, ou paga.
            'origin' => $booking->created_by_user ? 'secretaria' : 'site',
            'date' => $booking->event_date->toDateString(),
            'start_time' => $booking->start_time ? substr($booking->start_time, 0, 5) : null,
            'end_time' => $booking->end_time ? substr($booking->end_time, 0, 5) : null,
            'duration_hours' => $booking->duration_hours,
            'ends_next_day' => $booking->endsNextDay(),
            'place' => [
                'id' => $booking->place?->id,
                'name' => $booking->place?->name,
                'capacity' => $booking->place?->capacity,
                'max_guests' => (int) ($booking->place?->max_guests ?? 0),
            ],
            'place_group' => ['id' => $booking->place?->group?->id, 'name' => $booking->place?->group?->name],
            'event_type' => $booking->event_type,
            'expected_guests' => $booking->expected_guests,
            'notes' => $booking->notes,
            'base_price' => (float) $booking->base_price,
            'extra_guests' => (int) $booking->extra_guests,
            'extra_guest_price' => (float) $booking->extra_guest_price,
            'extras_total' => (float) $booking->extras_total,
            'base_tables' => (int) $booking->base_tables,
            'base_chairs' => (int) $booking->base_chairs,
            'tables_total' => $booking->tablesTotal(),
            'chairs_total' => $booking->chairsTotal(),
            'items' => $booking->items->map(fn ($item) => [
                'item_id' => $item->social_beverage_id,
                'beverage_id' => $item->social_beverage_id,
                'category' => $item->category,
                'name' => $item->name,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'tables_per_unit' => (int) $item->tables_per_unit,
                'chairs_per_unit' => (int) $item->chairs_per_unit,
            ])->values(),
            'items_total' => (float) $booking->items_total,
            'discount' => (float) $booking->discount,
            'total' => (float) $booking->total,
            'payment_due_at' => $booking->payment_due_at?->toIso8601String(),
            // Pagamento registrado: pelo site (Rede) ou baixa manual da
            // secretaria (pago fora da plataforma). Null enquanto não pago.
            'payment' => $payment ? [
                'paid_amount' => (float) $payment->paid_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'method' => $payment->payment_method,
                'method_label' => $payment->methodLabel(),
                'manual' => (bool) $payment->is_manual,
                'refunded_amount' => (float) $payment->refunded_amount,
            ] : null,
            'guest_limit' => $booking->guestLimit(),
            'guests' => $booking->guests->map(fn ($guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'cpf' => $guest->maskedCpf(),
            ])->values(),
            'guests_editable_until' => $booking->guestsEditableUntil()->toIso8601String(),
            'can_edit' => $booking->isEditable(),
            'can_pay' => $booking->canPay($now),
            'can_cancel' => $booking->isUnpaid(),
            'can_edit_guests' => $booking->canMemberEditGuests($now),
            'refusal_reason' => $booking->refusal_reason,
            'cancel_reason' => $booking->cancel_reason,
        ];
    }

    private function presentPlace(Place $place): array
    {
        return [
            'id' => $place->id,
            'name' => $place->name,
            'description' => $place->description,
            'amenities' => $place->amenities,
            'image' => $this->imageUrl($place->image),
            'price' => (float) $place->price,
            'capacity' => $place->capacity,
            'max_guests' => (int) $place->max_guests,
            'extra_guests' => (int) $place->extra_guests,
            'extra_guest_price' => (float) $place->extra_guest_price,
            'base_tables' => (int) $place->base_tables,
            'base_chairs' => (int) $place->base_chairs,
            'event_duration_hours' => $place->event_duration_hours,
            'event_earliest_start' => $place->event_earliest_start ? substr($place->event_earliest_start, 0, 5) : null,
            'event_latest_start' => $place->event_latest_start ? substr($place->event_latest_start, 0, 5) : null,
        ];
    }

    /** @return array{0: ?SocialBooking, 1: ?JsonResponse} */
    private function owned(Request $request, int $id): array
    {
        $member = $this->member($request);
        if (! $member) {
            return [null, $this->noSession()];
        }

        $booking = SocialBooking::where('id', $id)->where('member_id', $member->id)->first();

        if (! $booking) {
            return [null, response()->json(['message' => 'Reserva não encontrada.', 'reason' => SocialBookingException::NOT_OWNER], 404)];
        }

        return [$booking, null];
    }

    private function socialPlace(int $id): ?Place
    {
        $place = Place::with('group.weekdays')->where('status_id', 1)->find($id);

        return $place && $place->isSocial() ? $place : null;
    }

    private function member(Request $request): ?Member
    {
        $cpf = data_get($request->input('user'), 'username');

        return $cpf ? Member::where('cpf', $cpf)->first() : null;
    }

    private function noSession(): JsonResponse
    {
        return response()->json(['message' => 'Sessão inválida.'], 401);
    }

    private function placeNotFound(): JsonResponse
    {
        return response()->json(['message' => 'Espaço social não encontrado.', 'reason' => SocialBookingException::PLACE_NOT_SOCIAL], 404);
    }

    private function imageUrl(?string $image): ?string
    {
        // Arquivo do Lara ou link externo (imgur, postimg…).
        return ImageSource::url($image);
    }
}
