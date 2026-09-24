<?php

namespace App\Services\HomeAssistant;

use App\Exceptions\SelfServiceLightingException;
use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Models\LightingSelfServiceDate;
use App\Models\MemberLightingActivation;
use App\Models\Place;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Autoatendimento de iluminação: o sócio acende a luz da quadra pelo app.
 *
 * Existe porque no fim de semana não há reserva — o uso das quadras é livre, e
 * até aqui a única forma de acender a luz era pedir a alguém do clube. As
 * regras, todas elas, moram neste serviço:
 *
 *   1. só nas janelas de sábado/domingo (ou num feriado liberado no painel);
 *   2. só em espaços marcados com `self_service_lighting`;
 *   3. o sócio escolhe a duração, até o teto de `max_minutes` por acionamento,
 *      e sempre aparada no fim da janela;
 *   4. uma quadra por sócio ao mesmo tempo, sem teto diário.
 *
 * **A luz não é de ninguém.** Um acionamento não reserva a quadra: acabando o
 * tempo, qualquer sócio presente aciona de novo e a luz continua acesa sem
 * piscar — inclusive quem não acendeu da primeira vez. É por isso que a luz do
 * contator é sempre o *maior* prazo entre os acionamentos vigentes dele
 * (ver syncLight): um pedido curto no meio de um longo não pode encurtar o que
 * já estava valendo.
 *
 * O acionamento em si não reinventa nada: vira um comando manual comum
 * (ManualCommandService), o mesmo que o painel e o Telegram gravam, e o Home
 * Assistant aplica no polling seguinte. O que esta camada acrescenta é de quem
 * é a cota e quem acendeu — ver MemberLightingActivation.
 */
class SelfServiceLightingService
{
    /** Até onde nextWindow() procura antes de desistir. Cobre feriado emendado. */
    private const NEXT_WINDOW_SEARCH_DAYS = 21;

    /**
     * Exceções de calendário já lidas nesta requisição, por data.
     *
     * nextWindow() varre até três semanas, e a tela relê a disponibilidade a
     * cada meio minuto enquanto o contador corre: sem isto seria uma consulta
     * por dia varrido, em cada batida do laço. O serviço nasce e morre com a
     * requisição, então o cache não sobrevive para envelhecer.
     *
     * @var array<string, LightingSelfServiceDate|null>
     */
    private array $dates = [];

    public function __construct(
        private ManualCommandService $commands,
    ) {
    }

    /* ───────────────────────────── Janelas ───────────────────────────── */

    /**
     * A janela do dia informado, ou null quando o autoatendimento está fechado.
     *
     * A data sempre vence o dia da semana: um `block` cadastrado fecha o sábado
     * do torneio, e um `allow` abre a quinta-feira de Natal.
     */
    public function windowFor(Carbon $day): ?LightingWindow
    {
        $exception = $this->exceptionFor($day);

        if ($exception) {
            if ($exception->isBlock()) {
                return null;
            }

            // Sem horário próprio, o feriado usa a janela de feriado padrão.
            [$start, $end] = $exception->starts_at && $exception->ends_at
                ? [$exception->starts_at, $exception->ends_at]
                : (array) config('home_assistant.self_service.holiday_window', ['17:00', '21:00']);

            return $this->buildWindow($day, $start, $end, LightingWindow::SOURCE_DATE, $exception->reason);
        }

        $windows = (array) config('home_assistant.self_service.windows', []);
        $range = $windows[$day->dayOfWeek] ?? null;

        if (! $range) {
            return null;
        }

        return $this->buildWindow($day, $range[0], $range[1], LightingWindow::SOURCE_WEEKLY);
    }

    /** A janela que contém este instante, se houver uma aberta agora. */
    public function openWindowAt(Carbon $moment): ?LightingWindow
    {
        $window = $this->windowFor($moment);

        return $window && $window->contains($moment) ? $window : null;
    }

    /**
     * A próxima janela a partir deste instante — inclusive a de hoje, quando
     * ainda não começou.
     *
     * É o que a tela mostra quando o sócio abre o app numa terça: sem isto, a
     * única resposta possível seria "fechado", sem dizer até quando.
     */
    public function nextWindow(Carbon $moment): ?LightingWindow
    {
        $this->preloadDates($moment, $moment->copy()->addDays(self::NEXT_WINDOW_SEARCH_DAYS));

        for ($i = 0; $i <= self::NEXT_WINDOW_SEARCH_DAYS; $i++) {
            $window = $this->windowFor($moment->copy()->addDays($i)->startOfDay());

            if ($window && $window->end->greaterThan($moment)) {
                return $window;
            }
        }

        return null;
    }

    /** A exceção cadastrada para o dia, consultando no máximo uma vez por data. */
    private function exceptionFor(Carbon $day): ?LightingSelfServiceDate
    {
        $key = $day->toDateString();

        if (! array_key_exists($key, $this->dates)) {
            $this->dates[$key] = LightingSelfServiceDate::forDate($day)->first();
        }

        return $this->dates[$key];
    }

    /** Traz de uma vez as exceções do intervalo que nextWindow() vai varrer. */
    private function preloadDates(Carbon $from, Carbon $to): void
    {
        $rows = LightingSelfServiceDate::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (LightingSelfServiceDate $date) => $date->date->toDateString());

        for ($day = $from->copy()->startOfDay(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $key = $day->toDateString();

            // Sem sobrescrever o que já foi lido: dentro da requisição a
            // primeira leitura de uma data continua valendo.
            $this->dates[$key] ??= $rows->get($key);
        }
    }

    private function buildWindow(Carbon $day, string $start, string $end, string $source, ?string $reason = null): ?LightingWindow
    {
        $startsAt = $day->copy()->setTimeFromTimeString($start);
        $endsAt   = $day->copy()->setTimeFromTimeString($end);

        // Janela que virasse o dia não teria como ser executada: o comando
        // manual é truncado na meia-noite (ManualCommandService::expiryFor).
        // Melhor tratar como cadastro inválido do que abrir uma janela que o
        // acionamento não honraria.
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        return new LightingWindow($startsAt, $endsAt, $source, $reason);
    }

    /* ──────────────────────────── Elegibilidade ──────────────────────────── */

    /**
     * Espaços liberados para autoatendimento, com grupo e contator carregados.
     *
     * A tela pede grupo e depois quadra, então o grupo vem junto: são poucos
     * espaços e uma consulta só evita o N+1 no agrupamento.
     */
    public function eligiblePlaces(): Collection
    {
        return Place::selfServiceLighting()
            ->with(['group', 'contactor'])
            ->get();
    }

    /* ─────────────────────────── Estado atual ─────────────────────────── */

    /** O acionamento vigente do sócio, se ele tiver um. */
    public function activeFor(int $memberId, ?Carbon $moment = null): ?MemberLightingActivation
    {
        $moment = $moment ?: Carbon::now();

        return MemberLightingActivation::activeAt($moment)
            ->where('member_id', $memberId)
            ->with(['place.group', 'contactor'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Até quando a luz da quadra está garantida, somando todos os sócios.
     *
     * É o dado que a tela mostra em "acesa até 20:30" — e não o acionamento de
     * um sócio em particular, que pode acabar antes sem a luz apagar.
     */
    public function litUntil(int $placeId, ?Carbon $moment = null): ?Carbon
    {
        $moment = $moment ?: Carbon::now();

        $latest = MemberLightingActivation::activeAt($moment)
            ->where('place_id', $placeId)
            ->max('ends_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * O mesmo que litUntil(), para várias quadras numa consulta só.
     *
     * @param  list<int> $placeIds
     * @return array<int, string> place_id => ISO 8601; ausente quer dizer apagada
     */
    public function litUntilMany(array $placeIds, ?Carbon $moment = null): array
    {
        if ($placeIds === []) {
            return [];
        }

        $moment = $moment ?: Carbon::now();

        return MemberLightingActivation::activeAt($moment)
            ->whereIn('place_id', $placeIds)
            ->groupBy('place_id')
            ->selectRaw('place_id, MAX(ends_at) as ends_at')
            ->pluck('ends_at', 'place_id')
            ->map(fn ($endsAt) => Carbon::parse($endsAt)->toIso8601String())
            ->all();
    }

    /* ───────────────────────────── Acionamento ───────────────────────────── */

    /**
     * Acende a luz da quadra para este sócio, ou prolonga o que ele já tem.
     *
     * Acionar a quadra em que o sócio já está **prolonga** o acionamento dele:
     * é o mesmo gesto, e separá-lo em outro endpoint só faria a tela ter de
     * adivinhar qual chamar. Outra quadra, com uma acesa, é recusa.
     *
     * @param int|null $minutes quanto o sócio pediu; null usa o teto
     *
     * @throws SelfServiceLightingException quando alguma regra recusa — a
     *         exceção carrega o motivo em código, que a tela usa para escolher
     *         o que dizer.
     */
    public function activate(
        int $memberId,
        Place $place,
        ?int $minutes = null,
        ?Carbon $moment = null,
        ?string $origin = null
    ): MemberLightingActivation {
        $now = $moment ?: Carbon::now();

        $this->assertEligible($place);
        $window = $this->assertWindowOpen($now);

        $current = $this->activeFor($memberId, $now);

        // Uma quadra por sócio. A própria não conta: ali ele está prolongando.
        if ($current && $current->place_id !== $place->id) {
            throw SelfServiceLightingException::alreadyOn(
                'Você já está com a luz de outra quadra acesa.',
                ['active_activation' => $this->activationPayload($current, $now)]
            );
        }

        $granted = $this->minutesToGrant($window, $now, $minutes);
        $this->assertNoReservation($place, $now, $granted);

        return DB::transaction(function () use ($memberId, $place, $now, $granted, $origin, $current) {
            // O prazo conta a partir de agora, mesmo prolongando: "mais 40
            // minutos" é mais 40 a partir do toque, não do fim do anterior.
            $endsAt = $now->copy()->addMinutes($granted);

            if ($current) {
                // `starts_at` fica onde estava: o registro é da sessão de uso
                // inteira, e é ela que interessa a quem paga a conta de luz.
                $current->forceFill(['ends_at' => $endsAt])->save();
                $activation = $current;
            } else {
                $activation = MemberLightingActivation::create([
                    'member_id'    => $memberId,
                    'place_id'     => $place->id,
                    'contactor_id' => $place->contactor_id,
                    'starts_at'    => $now,
                    'ends_at'      => $endsAt,
                    'origin'       => $origin,
                ]);
            }

            $this->syncLight($place->contactor, $now, $origin ?: 'App do sócio');

            return $activation->refresh();
        });
    }

    /**
     * Devolve a quadra antes da hora.
     *
     * A luz só apaga se mais ninguém depender dela: um segundo sócio que
     * prolongou continua com o que pediu. Quem sai da quadra não apaga a luz de
     * quem ficou.
     */
    public function release(MemberLightingActivation $activation, ?Carbon $moment = null): MemberLightingActivation
    {
        $now = $moment ?: Carbon::now();

        return DB::transaction(function () use ($activation, $now) {
            $activation->forceFill(['released_at' => $now])->save();

            if ($activation->contactor) {
                $this->syncLight($activation->contactor, $now, 'App do sócio');
            }

            return $activation;
        });
    }

    /**
     * Põe a luz do contator no maior prazo entre os acionamentos vigentes.
     *
     * Um contator pode alimentar mais de um espaço, então a conta é por
     * contator, e não por quadra. Somar pelo maior é o que permite prolongar
     * sem piscar: um pedido de 30 minutos no meio de um de duas horas não
     * encurta o que já estava valendo.
     */
    private function syncLight(Contactor $contactor, Carbon $now, ?string $origin): ?HomeAssistantOverride
    {
        $latest = MemberLightingActivation::activeAt($now)
            ->where('contactor_id', $contactor->id)
            ->max('ends_at');

        if (! $latest) {
            // Apaga só se a luz atual for a nossa. Um "manter ligado" que o
            // painel deu por cima não pode morrer porque um sócio devolveu a
            // quadra — quem está no painel sabe o que está fazendo.
            if ($this->lightIsOurs($contactor)) {
                $this->commands->clear($contactor);
            }

            return null;
        }

        $minutes = max(1, (int) ceil($now->diffInSeconds(Carbon::parse($latest)) / 60));

        $override = $this->commands->apply(
            contactor: $contactor,
            state: 'on',
            minutes: $minutes,
            origin: $origin,
            userId: null,
        );

        // Todos os acionamentos vigentes passam a apontar para a luz que os
        // cobre: é por este vínculo que lightIsOurs() reconhece o comando como
        // do autoatendimento mais tarde.
        MemberLightingActivation::activeAt($now)
            ->where('contactor_id', $contactor->id)
            ->update(['home_assistant_override_id' => $override->id]);

        return $override;
    }

    /** O comando manual que está no contator agora nasceu do autoatendimento? */
    private function lightIsOurs(Contactor $contactor): bool
    {
        $currentId = $contactor->overrides()
            ->where('is_quick', true)
            ->orderByDesc('home_assistant_overrides.id')
            ->value('home_assistant_overrides.id');

        if (! $currentId) {
            return false;
        }

        return MemberLightingActivation::where('contactor_id', $contactor->id)
            ->where('home_assistant_override_id', $currentId)
            ->exists();
    }

    /* ─────────────────────────────── Regras ─────────────────────────────── */

    private function assertEligible(Place $place): void
    {
        if (! $place->self_service_lighting || ! $place->contactor_id) {
            throw SelfServiceLightingException::notEligible(
                'Esta quadra não está liberada para acionamento pelo app.'
            );
        }
    }

    private function assertWindowOpen(Carbon $now): LightingWindow
    {
        $window = $this->openWindowAt($now);

        if (! $window) {
            $next = $this->nextWindow($now);

            throw SelfServiceLightingException::closed(
                'O acionamento da luz está disponível apenas nos horários liberados.',
                ['next_window' => $next?->toArray()]
            );
        }

        $minimum = $this->minimumMinutes();

        if ($window->minutesLeft($now) < $minimum) {
            throw SelfServiceLightingException::tooLate(
                'Faltam menos de ' . $minimum . ' minutos para o encerramento do horário de hoje.',
                ['window' => $window->toArray()]
            );
        }

        return $window;
    }

    /**
     * Quadra com reserva confirmada não é acionável pelo app.
     *
     * A luz dela já acende sozinha pelo horário da reserva, e quem reservou tem
     * a quadra: deixar outro sócio "acender" ali seria vendê-la duas vezes.
     *
     * Olha todo o intervalo que o acionamento cobriria, e não só o instante
     * atual: acender às 17h30 numa quadra reservada às 18h tomaria a quadra de
     * quem pagou por ela.
     */
    private function assertNoReservation(Place $place, Carbon $now, int $minutes): void
    {
        $until = $now->copy()->addMinutes($minutes);

        $schedule = Schedule::where('place_id', $place->id)
            ->where('status_id', 1)
            ->where('start_schedule', '<', $until)
            ->where('end_schedule', '>', $now)
            ->orderBy('start_schedule')
            ->first();

        if (! $schedule) {
            return;
        }

        throw SelfServiceLightingException::reserved(
            'Esta quadra tem reserva confirmada neste horário.',
            [
                'reserved_from'  => $schedule->start_schedule->toIso8601String(),
                'reserved_until' => $schedule->end_schedule->toIso8601String(),
            ]
        );
    }

    /* ─────────────────────────────── Apoio ─────────────────────────────── */

    /**
     * Quantos minutos o sócio recebe de fato.
     *
     * O pedido é aparado duas vezes: pelo teto de um acionamento e pelo que
     * resta da janela. Pedir 2 horas às 22:30 de sábado devolve 30 minutos — e
     * é esse número, não o pedido, que a tela deve mostrar.
     */
    public function minutesToGrant(LightingWindow $window, Carbon $now, ?int $requested = null): int
    {
        $wanted = $requested ?: $this->maxMinutes();

        return min($wanted, $this->maxMinutes(), $window->minutesLeft($now));
    }

    public function maxMinutes(): int
    {
        return (int) (config('home_assistant.self_service.max_minutes') ?: 120);
    }

    public function minimumMinutes(): int
    {
        return (int) (config('home_assistant.self_service.min_minutes') ?: 15);
    }

    public function stepMinutes(): int
    {
        return (int) (config('home_assistant.self_service.step_minutes') ?: 15);
    }

    /**
     * Um acionamento como a tela precisa dele.
     *
     * `minutes_remaining` é do acionamento **deste sócio**; `lit_until` é da
     * quadra, somando todo mundo. Os dois diferem sempre que alguém prolongou
     * depois — e é a diferença que explica por que a luz não apagou.
     *
     * @return array<string, mixed>
     */
    public function activationPayload(MemberLightingActivation $activation, ?Carbon $moment = null): array
    {
        $moment = $moment ?: Carbon::now();

        return [
            'id'                => $activation->id,
            'place_id'          => $activation->place_id,
            'place_name'        => $activation->place?->name,
            'place_group'       => $activation->place?->group?->name,
            'starts_at'         => $activation->starts_at->toIso8601String(),
            'ends_at'           => $activation->ends_at->toIso8601String(),
            'minutes_remaining' => $activation->minutesRemaining($moment),
            'lit_until'         => $this->litUntil($activation->place_id, $moment)?->toIso8601String(),
            'released_at'       => $activation->released_at?->toIso8601String(),
        ];
    }
}
