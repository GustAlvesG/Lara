<?php

namespace Tests\Unit\HomeAssistant;

use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Models\HomeAssistantOverrideWindow;
use App\Models\Place;
use App\Models\Schedule;
use App\Models\Weekday;
use App\Services\HomeAssistant\ContactorState;
use App\Services\HomeAssistant\ContactorStateResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Decisão "ligar ou desligar" de um contator, sem banco: contatores,
 * agendamentos e reservas são montados em memória com as relações já
 * carregadas, do jeito que ContactorStateResolver os recebe.
 *
 * Datas de referência: sexta-feira 04/09/2026 (weekday id 6) e sábado
 * 05/09/2026 (weekday id 7) — id 1 é domingo, como no seed de weekdays.
 */
class ContactorStateResolverTest extends TestCase
{
    private const FRIDAY = 6;

    private int $nextOverrideId = 1;

    private function resolver(): ContactorStateResolver
    {
        return new ContactorStateResolver();
    }

    private function contactor(array $overrides = [], array $placeIds = [1]): Contactor
    {
        $contactor = (new Contactor())->forceFill(['id' => 1, 'name' => 'Quadra 1', 'entity_id' => 'switch.quadra_1']);

        $contactor->setRelation('places', collect($placeIds)->map(
            fn ($id) => (new Place())->forceFill(['id' => $id, 'name' => "Quadra {$id}"])
        ));
        $contactor->setRelation('overrides', collect($overrides));

        return $contactor;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2?: string}> $windows [início, fim, estado]
     * @param int[] $weekdayIds
     */
    private function override(string $mode, array $attributes = [], array $windows = [], array $weekdayIds = []): HomeAssistantOverride
    {
        $override = (new HomeAssistantOverride())->forceFill(array_merge([
            'id'         => $this->nextOverrideId++,
            'name'       => 'Agendamento',
            'mode'       => $mode,
            'priority'   => 0,
            'start_date' => null,
            'end_date'   => null,
            'is_active'  => true,
            'is_quick'   => false,
        ], $attributes));

        $override->setRelation('windows', collect($windows)->map(
            fn ($w) => (new HomeAssistantOverrideWindow())->forceFill([
                'turn_on_at'  => $w[0] . ':00',
                'turn_off_at' => $w[1] . ':00',
                'state'       => $w[2] ?? 'on',
            ])
        ));
        $override->setRelation('weekdays', collect($weekdayIds)->map(fn ($id) => (new Weekday())->forceFill(['id' => $id])));

        return $override;
    }

    /** Reservas agrupadas por place_id, como schedulesBetween() devolve. */
    private function reservations(array ...$periods): Collection
    {
        return collect($periods)
            ->map(fn ($p) => (new Schedule())->forceFill([
                'place_id'       => $p[2] ?? 1,
                'start_schedule' => $p[0],
                'end_schedule'   => $p[1],
                'status_id'      => 1,
            ]))
            ->groupBy('place_id');
    }

    private function at(string $moment, Contactor $contactor, ?Collection $reservations = null): ContactorState
    {
        return $this->resolver()->resolve($contactor, Carbon::parse($moment), $reservations ?? collect());
    }

    /* ───────────── Reservas ───────────── */

    public function test_reserva_liga_com_margem_de_cinco_minutos_antes_e_depois(): void
    {
        $contactor = $this->contactor();
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00']);

        $this->assertFalse($this->at('2026-09-04 18:54:00', $contactor, $reservations)->on);
        $this->assertTrue($this->at('2026-09-04 18:55:00', $contactor, $reservations)->on);
        $this->assertTrue($this->at('2026-09-04 20:04:59', $contactor, $reservations)->on);
        $this->assertFalse($this->at('2026-09-04 20:05:00', $contactor, $reservations)->on);

        $state = $this->at('2026-09-04 19:30:00', $contactor, $reservations);
        $this->assertSame(ContactorState::SOURCE_RESERVATION, $state->source);
        $this->assertSame('Reserva até 20:00 · Quadra 1', $state->reason());
    }

    public function test_reserva_de_outro_espaco_nao_liga_o_contator(): void
    {
        $contactor = $this->contactor(placeIds: [1]);
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00', 2]);

        $state = $this->at('2026-09-04 19:30:00', $contactor, $reservations);

        $this->assertFalse($state->on);
        $this->assertSame(ContactorState::SOURCE_IDLE, $state->source);
    }

    /* ───────────── Precedência ───────────── */

    public function test_acao_rapida_vence_agendamento_e_reserva(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', ['priority' => 999], [['18:00', '22:00', 'on']]),
            $this->override('manual_off', ['priority' => 1000, 'is_quick' => true, 'start_date' => '2026-09-04', 'end_date' => '2026-09-04']),
        ]);
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00']);

        $state = $this->at('2026-09-04 19:30:00', $contactor, $reservations);

        $this->assertFalse($state->on);
        $this->assertTrue($state->isManual());
    }

    public function test_acao_rapida_de_ontem_nao_vale_mais(): void
    {
        $contactor = $this->contactor([
            $this->override('manual_on', ['priority' => 1000, 'is_quick' => true, 'start_date' => '2026-09-03', 'end_date' => '2026-09-03']),
        ]);

        $this->assertSame(ContactorState::SOURCE_IDLE, $this->at('2026-09-04 10:00:00', $contactor)->source);
    }

    public function test_maior_prioridade_vence_e_no_empate_vence_o_mais_recente(): void
    {
        $low = $this->override('manual_on', ['priority' => 1]);
        $high = $this->override('manual_off', ['priority' => 5]);
        $this->assertFalse($this->at('2026-09-04 10:00:00', $this->contactor([$low, $high]))->on);

        $older = $this->override('manual_off', ['priority' => 3]);
        $newer = $this->override('manual_on', ['priority' => 3]);
        $state = $this->at('2026-09-04 10:00:00', $this->contactor([$newer, $older]));
        $this->assertTrue($state->on);
        $this->assertSame($newer->id, $state->override->id);
    }

    public function test_agendamento_pausado_ou_fora_do_periodo_nao_tem_efeito(): void
    {
        $contactor = $this->contactor([
            $this->override('manual_on', ['is_active' => false]),
            $this->override('manual_on', ['start_date' => '2026-09-05']),
            $this->override('manual_on', ['end_date' => '2026-09-03']),
            $this->override('manual_on', [], [], [self::FRIDAY + 1]),
        ]);

        $this->assertSame(ContactorState::SOURCE_IDLE, $this->at('2026-09-04 10:00:00', $contactor)->source);
    }

    /* ───────────── Faixas de horário ───────────── */

    public function test_fora_das_faixas_o_agendamento_devolve_a_decisao_as_reservas(): void
    {
        // Antes: um "Por horário" 06:00–08:00 apagava a quadra reservada às 19:00.
        $contactor = $this->contactor([
            $this->override('schedule_override', ['priority' => 10], [['06:00', '08:00', 'on']]),
        ]);
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00']);

        $this->assertSame(ContactorState::SOURCE_OVERRIDE, $this->at('2026-09-04 07:00:00', $contactor, $reservations)->source);

        $evening = $this->at('2026-09-04 19:30:00', $contactor, $reservations);
        $this->assertTrue($evening->on);
        $this->assertSame(ContactorState::SOURCE_RESERVATION, $evening->source);
    }

    public function test_fora_das_faixas_vale_o_agendamento_de_prioridade_menor(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', ['priority' => 10], [['06:00', '08:00', 'off']]),
            $this->override('manual_on', ['priority' => 1]),
        ]);

        $this->assertFalse($this->at('2026-09-04 07:00:00', $contactor)->on);
        $this->assertTrue($this->at('2026-09-04 12:00:00', $contactor)->on);
    }

    public function test_faixa_desliga_mesmo_com_reserva(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', [], [['19:00', '23:00', 'off']]),
        ]);
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00']);

        $state = $this->at('2026-09-04 19:30:00', $contactor, $reservations);

        $this->assertFalse($state->on);
        $this->assertSame(ContactorState::SOURCE_OVERRIDE, $state->source);
    }

    public function test_faixas_encostadas_nao_se_sobrepoem(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', [], [['18:00', '20:00', 'on'], ['20:00', '22:00', 'off']]),
        ]);

        $this->assertTrue($this->at('2026-09-04 19:59:59', $contactor)->on);
        $this->assertFalse($this->at('2026-09-04 20:00:00', $contactor)->on);
        $this->assertSame(ContactorState::SOURCE_OVERRIDE, $this->at('2026-09-04 21:59:00', $contactor)->source);
        $this->assertSame(ContactorState::SOURCE_IDLE, $this->at('2026-09-04 22:00:00', $contactor)->source);
    }

    public function test_madrugada_de_faixa_que_vira_a_meia_noite_pertence_ao_dia_anterior(): void
    {
        // Regra só de sexta, 22:00–02:00
        $contactor = $this->contactor([
            $this->override('schedule_override', [], [['22:00', '02:00', 'on']], [self::FRIDAY]),
        ]);

        $this->assertTrue($this->at('2026-09-04 23:00:00', $contactor)->on, 'sexta 23:00');
        $this->assertTrue($this->at('2026-09-05 01:30:00', $contactor)->on, 'madrugada de sábado');
        $this->assertFalse($this->at('2026-09-05 23:00:00', $contactor)->on, 'sábado 23:00');
        $this->assertFalse($this->at('2026-09-04 01:30:00', $contactor)->on, 'madrugada de sexta é da quinta');
    }

    public function test_periodo_da_faixa_que_vira_a_meia_noite_segue_o_dia_em_que_ela_comecou(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', ['end_date' => '2026-09-04'], [['22:00', '02:00', 'on']]),
        ]);

        $this->assertTrue($this->at('2026-09-05 01:00:00', $contactor)->on);
        $this->assertFalse($this->at('2026-09-06 01:00:00', $contactor)->on);
    }

    /* ───────────── Linha do tempo ───────────── */

    public function test_linha_do_tempo_agrupa_trechos_de_mesmo_estado_e_motivo(): void
    {
        $contactor = $this->contactor([
            $this->override('schedule_override', ['name' => 'Manhã'], [['06:00', '08:00', 'on']]),
        ]);
        $reservations = $this->reservations(['2026-09-04 19:00:00', '2026-09-04 20:00:00']);

        $segments = $this->resolver()->timeline($contactor, Carbon::parse('2026-09-04'), $reservations);

        $this->assertSame(0, $segments[0]['start']);
        $this->assertSame(1440, end($segments)['end']);

        $on = array_values(array_filter($segments, fn ($s) => $s['on']));
        $this->assertCount(2, $on);
        $this->assertSame([360, 480, 'override'], [$on[0]['start'], $on[0]['end'], $on[0]['source']]);
        $this->assertSame([1135, 1205, 'reservation'], [$on[1]['start'], $on[1]['end'], $on[1]['source']]);

        // Trechos contíguos, sem buraco nem sobreposição
        for ($i = 1; $i < count($segments); $i++) {
            $this->assertSame($segments[$i - 1]['end'], $segments[$i]['start']);
        }
    }
}
