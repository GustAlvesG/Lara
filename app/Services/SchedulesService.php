<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Console\Commands\ExpirePendingSchedules;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Place;
use App\Models\Member;
use App\Models\ScheduleRules;
use App\Models\Contactor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use PDF; // Facade do pacote barryvdh/laravel-dompdf
use App\Http\Controllers\MemberController;
use App\Services\ScheduleRulesService;
use App\Services\MemberService;
use App\Services\EmailService;
use App\Services\RedeItauService;


class SchedulesService
{

    protected $scheduleRulesService;
    protected $memberService;
    protected $emailService;
    protected $redeItauService;

    public function __construct()
    {
        $this->scheduleRulesService = new ScheduleRulesService();
        $this->memberService = new MemberService();
        $this->emailService = new EmailService();
        $this->redeItauService = new RedeItauService();
    }

    public function getShedulesByPlace($place_id, $date = null){
        $schedules = Schedule::where('place_id', $place_id)
            ->when($date, function ($query) use ($date) {
                return $query->whereDate('start_schedule', $date->toDateString());
            })
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'No schedules found for this place.'], 404);
        }

        return response()->json(['schedules' => $schedules], 200);
    }

    public function getScheduleByMember($member_id){
        $schedules = Schedule::withoutGlobalScopes()
            ->where('member_id', $member_id)->get();
            $schedules = $schedules->load(['place']);
            if ($schedules->isEmpty()) {
                return response()->json(['message' => 'No schedules found for this member.'], 404);
            }
            return response()->json(['schedules' => $schedules], 200);
    }

    public function updateSchedulesStatus($data){
        $schedules_ids = [];
        $schedules = [];
        $user = Auth()->user();
        $payments_ids = [];
        $cancelled = [];
        $isCancelling = isset($data['action_status']) && (int) $data['action_status'] === 0;
        foreach ($data['selected_reservations'] as $schedule_id) {
            $schedule = Schedule::find($schedule_id);
            if ($schedule) {
                $schedule->status_id = $data['action_status'];

                $schedule->updated_by_user = $user->id;

                if ($isCancelling) {
                    $schedule->cancel_reason = $data['cancel_reason'];
                    $schedule->cancelled_by = $user->id;
                    $schedule->cancelled_at = Carbon::now();
                }

                $schedule->save();

                if ($isCancelling) {
                    $cancelled[] = $schedule;
                }

                if(isset($data['refund_payment'])){
                    if (!in_array($schedule->schedule_payment_id, $payments_ids)) {
                        $payments_ids[] = $schedule->schedule_payment_id;
                    }
                }
            }
        }
        $response = [];
        $refundRequested = isset($data['refund_payment']) && count($payments_ids) > 0;

        // Quanto cada pagamento já tinha de estorno antes desta ação: a diferença
        // depois da chamada é exatamente o que foi estornado agora, e é esse
        // valor — não o preço da reserva — que o sócio é informado no e-mail.
        $refundedBefore = $refundRequested ? $this->refundedAmounts($payments_ids) : [];

        try {
            if ($refundRequested) {
                $response = $this->redeItauService->beginRefund($payments_ids, $user->id);
            }
        } finally {
            // `finally`: se o estorno falhar, o cancelamento já está gravado e o
            // sócio precisa ser avisado do mesmo jeito — só que sem prometer
            // devolução nenhuma. A exceção continua subindo para a tela do admin.
            if ($isCancelling && $cancelled) {
                $deltas = [];
                foreach ($refundRequested ? $this->refundedAmounts($payments_ids) : [] as $paymentId => $after) {
                    $delta = round($after - ($refundedBefore[$paymentId] ?? 0), 2);
                    if ($delta > 0) {
                        $deltas[$paymentId] = $delta;
                    }
                }

                $this->notifyScheduleStatus($cancelled, null, [
                    'refund_requested' => $refundRequested,
                    'refund_deltas' => $deltas,
                ]);
            }
        }

        return $response;
    }

    /**
     * Total já estornado de cada pagamento, indexado por id.
     *
     * @param  array<int, int|null>  $payments_ids
     * @return array<int, float>
     */
    private function refundedAmounts(array $payments_ids): array
    {
        return SchedulePayment::whereIn('id', array_filter($payments_ids))
            ->pluck('refunded_amount', 'id')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    public function getSchedules($date = null)
    {
        if (!$date) {
            $date = Carbon::now()->toDateString();
        }

        $allPlacesAndTimes = Place::where('status_id', 1)->with(['group'])->get();

        foreach ($allPlacesAndTimes as $place) {
            $options = $this->scheduleRulesService->getTimeOptions($place->id, $date);
            $place->time_options = $options;
        }

        //Group by place group
        $allPossibleSchedules = $allPlacesAndTimes->groupBy(function ($item) {
            return $item->group->name;
        });
        return $allPossibleSchedules;
    }

    public function createSchedule(Request $request)
    {
        if (isset($request['cpf'])) { 
            //Clean cpf to have only numbers
            $request['member_id'] = $this->memberService->memberByCpf($request);
        }

        $schedules = [];
        $createdSchedules = [];

        foreach ($request->input('selected_slots') as $slot) {
            $time_start = explode(" - ", $slot)[0];
            $time_end = explode(" - ", $slot)[1];
            if (!$this->checkColide($time_start, $time_end, $request['place_id'], $request->input('date'), $request['member_id'])[0] == null) {
                throw new \Exception("Horário colide com outro agendamento.");
            }
            if (!$this->isValidScheduleTime($request['place_id'], $time_start, $time_end, $request->input('date'))) {
                throw new \Exception("Horário inválido para o local selecionado.");
            }
            if (Auth()->check()) {
                $request['created_by_user'] = Auth()->user()->id;
            }

            $schedule = $this->persistSchedule([
                'place_id' => $request['place_id'],
                'member_id' => $request['member_id'],
                'start_schedule' => $request->input('date') . ' ' . $time_start,
                'end_schedule' => $request->input('date') . ' ' . $time_end,
                'status_id' => $request->input('status_id') ?? 1,
                // Se nenhum preço foi enviado explicitamente, usa o preço real do Place —
                // nunca confia em um preço de cliente sem contrapartida no cadastro.
                'price' => $request['price'] ?? optional(Place::find($request['place_id']))->price,
                'created_by_user' => $request['created_by_user'] ?? null,
            ]);

            $createdSchedules[] = $schedule;
            $schedules[] = response()->json(['schedule' => $schedule], 201);
        }

        // O e-mail sai a partir do que foi de fato gravado (status e preço reais
        // dos agendamentos), e não do que veio na requisição: no fluxo do app
        // externo o preço nem chega a ser enviado — é resolvido pelo Place.
        $this->notifyScheduleStatus($createdSchedules);

        return $schedules;
    }

    /**
     * Envia ao sócio o e-mail correspondente ao estado atual dos agendamentos.
     *
     * Ponto único de saída dos e-mails de agendamento: criação (pendente ou
     * confirmado na hora), confirmação do pagamento vinda do app externo e
     * cancelamento pelo painel. Só notifica estados que interessam ao sócio:
     * pendente (3), confirmado (1) e cancelado (0) — expirado não vira e-mail.
     *
     * @param  iterable<int, Schedule>  $schedules
     * @param  array<string, mixed>  $extra  dados do contexto (ex.: estorno do cancelamento)
     */
    public function notifyScheduleStatus(iterable $schedules, ?SchedulePayment $payment = null, array $extra = []): bool
    {
        $schedules = EloquentCollection::make(collect($schedules)->filter()->values()->all());

        if ($schedules->isEmpty()) {
            return false;
        }

        // Cancelamento em lote pelo painel pode juntar reservas de sócios
        // diferentes: cada um recebe um e-mail com as suas, e só com as suas.
        if ($schedules->pluck('member_id')->unique()->count() > 1) {
            $sent = false;
            foreach ($schedules->groupBy('member_id') as $group) {
                $sent = $this->notifyScheduleStatus($group, $payment, $extra) || $sent;
            }

            return $sent;
        }

        $statuses = $schedules->pluck('status_id')->unique();

        // Lote misto (parte confirmada, parte pendente) não vira um e-mail só:
        // cada estado é notificado separadamente para não informar errado.
        if ($statuses->count() > 1) {
            $sent = false;
            foreach ($schedules->groupBy('status_id') as $group) {
                $sent = $this->notifyScheduleStatus($group, $payment, $extra) || $sent;
            }

            return $sent;
        }

        $type = match ((int) $statuses->first()) {
            0 => 'schedule.cancel',
            1 => 'schedule.confirm',
            3 => 'schedule.pending',
            default => null,
        };

        if ($type === null) {
            return false;
        }

        // Notificar é efeito colateral: se o conteúdo não puder ser montado
        // (sócio/espaço ausente, banco de sócios fora do ar), registra e segue —
        // o agendamento e o pagamento já estão gravados e não podem cair por isso.
        try {
            $data = $this->buildScheduleMailData($schedules, $type, $payment, $extra);
        } catch (\Throwable $e) {
            Log::error('Falha ao montar o e-mail de agendamento.', [
                'type' => $type,
                'schedule_ids' => $schedules->pluck('id')->all(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($data === null) {
            return false;
        }

        return $this->emailService->sendScheduleMail($data);
    }

    /**
     * Monta o conteúdo do e-mail a partir dos agendamentos gravados.
     *
     * Cada horário vira um item com local, data, faixa de horário e valor, de
     * modo que a mensagem descreva a reserva exatamente como ela está no banco
     * — inclusive quando o sócio reserva vários horários de uma vez.
     *
     * @param  EloquentCollection<int, Schedule>  $schedules
     * @param  array<string, mixed>  $extra
     */
    private function buildScheduleMailData(EloquentCollection $schedules, string $type, ?SchedulePayment $payment, array $extra = []): ?array
    {
        $schedules = $schedules->sortBy('start_schedule')->values();
        $schedules->loadMissing(['place.group', 'member']);

        $member = $schedules->first()->member ?? Member::find($schedules->first()->member_id);

        if (!$member) {
            Log::warning('E-mail de agendamento não enviado: sócio não encontrado.', [
                'schedule_ids' => $schedules->pluck('id')->all(),
            ]);

            return null;
        }

        $items = $schedules->map(function (Schedule $schedule) {
            $place = $schedule->place;
            $start = $schedule->start_schedule;
            $end = $schedule->end_schedule;

            return [
                'id' => $schedule->id,
                'place_name' => $place
                    ? trim(optional($place->group)->name . ' - ' . $place->name, ' -')
                    : 'Espaço não identificado',
                'date' => $start->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY'),
                'weekday' => ucfirst($start->locale('pt_BR')->isoFormat('dddd')),
                'time' => $start->format('H:i') . ' às ' . $end->format('H:i'),
                'duration' => $this->humanDuration((int) $start->diffInMinutes($end)),
                'price' => number_format((float) $schedule->price, 2, ',', '.'),
            ];
        })->all();

        $total = $schedules->sum(fn (Schedule $schedule) => (float) $schedule->price);

        // Assunto já traz local, dia e hora: quem recebe entende a mensagem
        // pela lista da caixa de entrada, sem precisar abrir.
        $first = $schedules->first();
        $extras = $schedules->count() - 1;
        $subject = match ($type) {
                'schedule.confirm' => 'Agendamento confirmado',
                'schedule.cancel' => 'Agendamento cancelado',
                default => 'Agendamento aguardando pagamento',
            }
            . ' - ' . $items[0]['place_name']
            . ', ' . $first->start_schedule->format('d/m')
            . ' às ' . $first->start_schedule->format('H:i')
            . ($extras > 0 ? " (+{$extras} " . ($extras > 1 ? 'horários' : 'horário') . ')' : '');

        $data = [
            'email' => $member->email,
            'name' => $member->name,
            'member_title' => $member->title ?? null,
            'type' => $type,
            'subject' => $subject,
            'schedule_ids' => $schedules->pluck('id')->all(),
            'items' => $items,
            'total' => number_format($total, 2, ',', '.'),
            'issued_at' => Carbon::now()->format('d/m/Y H:i'),

            // Campos "achatados" mantidos para compatibilidade com quem já lia
            // place_name/date/time/price direto no template.
            'place_name' => $items[0]['place_name'],
            'date' => $items[0]['date'],
            'time' => implode(' / ', array_column($items, 'time')),
            'price' => number_format($total, 2, ',', '.'),
        ];

        if ($type === 'schedule.pending') {
            $holdMinutes = ExpirePendingSchedules::HOLD_MINUTES;
            $createdAt = $schedules->min('created_at') ?? Carbon::now();

            $data['hold_minutes'] = $holdMinutes;
            $data['hold_deadline'] = $createdAt->copy()->addMinutes($holdMinutes)->format('d/m/Y H:i');
        }

        if ($type === 'schedule.confirm' || $type === 'schedule.cancel') {
            $payment = $payment ?: $first->schedulePayment;

            if ($payment) {
                $data['payment'] = [
                    'method' => $this->paymentMethodLabel($payment->payment_method),
                    'amount' => number_format((float) $payment->paid_amount, 2, ',', '.'),
                    'paid_at' => optional($payment->paid_at)->format('d/m/Y H:i'),
                    'reference' => $payment->payment_integration_id,
                ];
            }
        }

        if ($type === 'schedule.cancel') {
            $data['cancel_reason'] = $first->cancel_reason;
            $data['cancelled_at'] = optional($first->cancelled_at)->format('d/m/Y H:i');

            // Só é estorno o que o gateway de fato devolveu agora (diferença
            // medida em updateSchedulesStatus). Sem isso, o e-mail prometeria
            // devolução em cancelamento sem pagamento — ou quando a chamada falhou.
            $deltas = $extra['refund_deltas'] ?? [];
            $refunded = $schedules->pluck('schedule_payment_id')
                ->filter()
                ->unique()
                ->sum(fn ($paymentId) => (float) ($deltas[$paymentId] ?? 0));

            $data['refund'] = [
                'requested' => (bool) ($extra['refund_requested'] ?? false),
                'amount' => $refunded > 0 ? number_format($refunded, 2, ',', '.') : null,
                'paid' => (bool) $payment,
            ];
        }

        return $data;
    }

    private function humanDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours && $rest) {
            return "{$hours}h{$rest}min";
        }

        return $hours ? "{$hours}h" : "{$rest}min";
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'credit', 'credit_card', 'cartao_credito' => 'Cartão de crédito',
            'debit', 'debit_card', 'cartao_debito' => 'Cartão de débito',
            'pix' => 'Pix',
            '' => 'Não informado',
            default => ucfirst((string) $method),
        };
    }


    private function isValidScheduleTime($place_id, $time_start, $time_end, $date){
        $options = $this->scheduleRulesService->getTimeOptions($place_id, $date);
        // dd($options);
        foreach ($options as $option) {

            if ($option['start_time'] == $time_start && $option['end_time'] == $time_end) {
                return true;
            }
        }
        return false;
    }

    public function store(Request $request)
    {
        return response()->json(['schedule' => $this->persistSchedule($request->all())], 201);
    }

    /**
     * Grava o agendamento e devolve o model — quem chama precisa do registro em
     * si (preço e status reais) para montar o e-mail, não de uma resposta HTTP.
     */
    private function persistSchedule(array $attributes): Schedule
    {
        try {
            return Schedule::create($attributes);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Backstop de banco (índice único em active_slot_key): outra requisição
            // concorrente reservou esse horário entre a checagem de colisão e o insert.
            throw new \Exception("Horário colide com outro agendamento.");
        }
    }


    public function checkColide($slotStartTime, $slotEndTime, $place_id, $date, $member_id = null){
        $slotStart = strtotime($slotStartTime);
        $slotEnd = strtotime($slotEndTime);

        // Fetch existing schedules for the given place
        $existingSchedules = Schedule::where('place_id', $place_id)
            ->whereNotIn('status_id', [0, 4]) // Exclude cancelled and pending schedules
            ->whereDate('start_schedule', $date)
            ->get();


        foreach ($existingSchedules as $schedule) {
            $scheduleStart = strtotime(date('H:i', strtotime($schedule->start_schedule)));
            $scheduleEnd = strtotime(date('H:i', strtotime($schedule->end_schedule)));

            // Check for overlap
            if (!($slotEnd <= $scheduleStart || $slotStart >= $scheduleEnd)) {
                $member = Member::find($schedule->member_id);
                return [$member, $schedule->status_id, "Horário reservado por outro associado.", $schedule]; // Collision detected
            }
        }
        if ($member_id) {
            // Check for member-specific schedules
            $memberSchedules = Schedule::where('member_id', $member_id)
                ->whereIn('status_id', [1, 3]) 
                ->whereDate('start_schedule', $date)
                ->get();
            
            foreach ($memberSchedules as $schedule) {
                $scheduleStart = strtotime(date('H:i', strtotime($schedule->start_schedule)));
                $scheduleEnd = strtotime(date('H:i', strtotime($schedule->end_schedule)));

                // Check for overlap
                if (!($slotEnd <= $scheduleStart || $slotStart >= $scheduleEnd)) {
                    $member = Member::find($schedule->member_id);
                    return [$member, $schedule->status_id, "Você já possui um agendamento nesse horário.", $schedule ]; // Collision detected
                }
            }
        }


        return [null, null, null, null]; // No collision
    }

    public function countMemberSchedulesInPlaceGroupOnDate($group, $member_id, $date){
        $placesIds = $group->places->pluck('id')->toArray();

        $count = Schedule::whereIn('place_id', $placesIds)
            ->where('member_id', $member_id)
            ->whereIn('status_id', [1, 3]) // Exclude cancelled and pending schedules
            ->whereDate('start_schedule', $date)
            ->count();

        $remaining = $group->daily_limit - $count;

        $response = [
            'limit' => $group->daily_limit,
            'remaining' => $remaining 
        ];

        return $response;
    }

    public function homeAssistantAutomation(){

        $now = Carbon::now();

        $schedules = Schedule::where('status_id', 1)
            ->whereDate('start_schedule', $now->toDateString())
            ->get();

        $places_schedules = [];

        foreach ($schedules as $schedule) {
            $schedule->lights_on  = $schedule->start_schedule->copy()->subMinutes(5);
            $schedule->lights_off = $schedule->end_schedule->copy()->addMinutes(5);

            if ($now->between($schedule->lights_on, $schedule->lights_off)) {
                $places_schedules[] = $schedule->place_id;
            }
        }

        $places_schedules = array_unique($places_schedules);

        $contactors_list = Contactor::with([
            'places',
            'overrides' => fn ($q) => $q->where('is_active', true)->with(['weekdays', 'windows']),
        ])->get();

        $contactors = [];

        foreach ($contactors_list as $contactor) {
            // Agendamento vigente de maior prioridade (manual ou por horário)
            $override = $contactor->effectiveOverride($now);

            if ($override) {
                $contactors[$contactor->entity_id] = $override->resolvedState($now);
                continue;
            }

            // Sem override: usa lógica padrão — verifica se algum place do contator tem reserva ativa
            $hasActiveSchedule = $contactor->places->contains(fn($place) => in_array($place->id, $places_schedules));
            $contactors[$contactor->entity_id] = $hasActiveSchedule;
        }

        return response()->json(['contactors' => $contactors], 200);
    }


        




}

