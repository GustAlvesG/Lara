<?php

namespace App\Services\HomeAssistant;

use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comando manual sobre um contator: "liga agora", "desliga agora", "volta ao automático".
 *
 * É o mesmo caminho para o botão do painel e para a API consumida pelo Home
 * Assistant — a Lara é a fonte da verdade, e o HA só aplica o que ela responde.
 *
 * O comando é gravado como um agendamento de prioridade 1000 marcado com
 * `is_quick`, acima do teto de 999 dos agendamentos do painel. Quem faz a
 * comparação é ContactorStateResolver, via HomeAssistantOverride::stateAt().
 */
class ManualCommandService
{
    /** Acima do teto dos agendamentos do painel (validação: max:999). */
    public const PRIORITY = 1000;

    public function __construct(private ContactorStateResolver $resolver)
    {
    }

    /**
     * Liga ou desliga o contator até expirar.
     *
     * @param string   $state   'on' ou 'off'
     * @param int|null $minutes duração; null vale até o fim do dia (botão do painel)
     */
    public function apply(
        Contactor $contactor,
        string $state,
        ?int $minutes = null,
        ?string $origin = null,
        ?int $userId = null
    ): HomeAssistantOverride {
        $on = $state === 'on';

        return DB::transaction(function () use ($contactor, $on, $minutes, $origin, $userId) {
            // Um comando substitui o anterior; dois manuais no mesmo contator nunca coexistem.
            $this->clear($contactor);

            $override = HomeAssistantOverride::create([
                'name'       => $on ? 'Ligado manualmente' : 'Desligado manualmente',
                'mode'       => $on ? 'manual_on' : 'manual_off',
                'priority'   => self::PRIORITY,
                'start_date' => Carbon::today(),
                'end_date'   => Carbon::today(),
                'expires_at' => $this->expiryFor($minutes),
                'is_active'  => true,
                'is_quick'   => true,
                'created_by' => $userId,
                'origin'     => $origin,
            ]);

            $override->contactors()->attach($contactor->id);

            return $override;
        });
    }

    /** Remove os comandos manuais do contator: ele volta a seguir agendamentos e reservas. */
    public function clear(Contactor $contactor): int
    {
        $ids = $contactor->overrides()->where('is_quick', true)->pluck('home_assistant_overrides.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        // Pivots e janelas caem por cascade (ver migration 2026_06_09_100004).
        return HomeAssistantOverride::whereIn('id', $ids)->delete();
    }

    /**
     * Estado do contator agora, recalculado do banco.
     *
     * É o que a API devolve depois de gravar: o pedido pode não virar estado —
     * um "liga" perde para um agendamento de prioridade maior, por exemplo.
     */
    public function currentState(Contactor $contactor): ContactorState
    {
        $now = Carbon::now();

        $fresh = $contactor->load([
            'places',
            'overrides' => fn ($q) => $q->with(['weekdays', 'windows']),
        ]);

        return $this->resolver->resolve($fresh, $now, $this->resolver->schedulesBetween($now, $now));
    }

    /**
     * Quando o comando perde a validade.
     *
     * Truncado na virada do dia porque a vigência do agendamento continua sendo
     * por data (`start_date`/`end_date` = hoje): passada a meia-noite ele não se
     * aplicaria mais de qualquer jeito, e uma `expires_at` de amanhã só
     * enganaria quem lê a tabela.
     */
    public function expiryFor(?int $minutes): Carbon
    {
        $now = Carbon::now();
        $endOfDay = $now->copy()->endOfDay();

        if ($minutes === null) {
            return $endOfDay;
        }

        return $now->copy()->addMinutes($minutes)->min($endOfDay);
    }
}
