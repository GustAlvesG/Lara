<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Status;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Reservas repaginado. A agenda continua listando os horários de cada local
 * para a reserva ser feita direto (marcar livres, achar o sócio, confirmar);
 * listas de agendamentos e pagamentos com busca no servidor; modalidades em
 * cartões com busca na página.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class ReservasScreensTest extends TestCase
{
    use RendersScreens;

    private function recepcao()
    {
        return $this->usuario(new UserAccess([P::RESERVAS_AGENDAMENTOS, P::RESERVAS_PAGAMENTOS, P::RESERVAS_CONFIGURAR]));
    }

    private function miolo(string $html): string
    {
        $inicio = strpos($html, '<main>');
        $fim = strpos($html, '<footer');

        return substr($html, (int) $inicio, $fim === false ? null : $fim - (int) $inicio);
    }

    /** Um local como o SchedulesService entrega: um horário de cada estado. */
    private function agenda(): array
    {
        $slot = fn (string $start, string $end, array $extra = []) => array_merge(['start_time' => $start, 'end_time' => $end], $extra);

        return [
            'Tênis' => [[
                'id' => 7, 'name' => 'Quadra 1', 'price' => 80, 'image' => null, 'group' => ['id' => 3],
                'time_options' => [
                    $slot('07:00', '08:00', ['past_date' => true]),
                    $slot('08:00', '09:00', ['in_progress' => true, 'price' => 40, 'price_factor' => 0.5, 'remaining_minutes' => 30]),
                    $slot('09:00', '10:00'),
                    $slot('10:00', '11:00', ['colides' => ['id' => 55, 'created_at' => now()->toDateTimeString()], 'colided_member' => ['name' => 'Ana Sócia', 'title' => '123'], 'colided_status_id' => 1]),
                    $slot('11:00', '12:00', ['colides' => ['id' => 56, 'created_at' => now()->subMinutes(4)->toDateTimeString()], 'colided_member' => ['name' => 'Bruno Sócio', 'title' => '456'], 'colided_status_id' => 3]),
                    $slot('12:00', '13:00', ['excluded_by_rule' => ['name' => 'Manutenção']]),
                ],
            ]],
        ];
    }

    public function test_agenda_mantem_a_lista_de_horarios_para_reservar(): void
    {
        $html = $this->tela($this->recepcao(), 'schedule.index', [], 'location.index', [
            'modalities' => $this->agenda(), 'date' => Carbon::today()->toDateString(),
        ]);

        // Horário livre é botão que marca o checkbox enviado no POST.
        $this->assertStringContainsString("toggleSlot(this, '7', '09:00')", $html);
        $this->assertStringContainsString('name="selected_slots[]"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        // Em andamento mostra o valor proporcional.
        $this->assertStringContainsString('resta 30 min', $html);
        $this->assertStringContainsString('(50%)', $html);
        // Ocupado leva ao agendamento; pendente mostra a espera.
        $this->assertStringContainsString(route('schedule.show', ['id' => 55]), $html);
        $this->assertStringContainsString('Pendente ·', $html);
        $this->assertStringContainsString('Manutenção', $html);
        $this->assertStringContainsString('Já passou', $html);
        // O formulário da reserva continua o mesmo.
        $this->assertStringContainsString('action="' . route('schedule.store.web') . '"', $html);
        $this->assertStringContainsString('id="member-search-7"', $html);
        $this->assertStringContainsString('id="submit-btn-7"', $html);
        // O cartão do local não corta a lista de sócios encontrados.
        $this->assertDoesNotMatchRegularExpression('#class="court-card[^"]*overflow-hidden#', $html);
        $this->assertMatchesRegularExpression('#id="form-container-7" class="[^"]*relative z-20#', $html);
        // Preço vem do local do formulário (antes saía do último local do laço).
        $this->assertStringContainsString('data-price="80"', $html);
        $this->assertStringContainsString('form.dataset.price', $html);
        // Busca na página, sem placeholder externo nem paleta antiga.
        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('id="agenda"', $html);
        $this->assertStringNotContainsString('placehold.co', $html);
        $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo|green|yellow)-\d{2,3}\b#', $this->miolo($html));
    }

    public function test_lista_de_agendamentos_busca_socio_com_filtros(): void
    {
        $place = (new Place)->forceFill(['id' => 7, 'name' => 'Quadra 1']);
        $place->setRelation('group', (new PlaceGroup)->forceFill(['id' => 3, 'name' => 'Tênis']));
        $schedule = (new Schedule)->forceFill([
            'id' => 90, 'status_id' => 3, 'price' => 80,
            'start_schedule' => '2026-10-01 09:00:00', 'end_schedule' => '2026-10-01 10:00:00',
        ]);
        $schedule->setRelation('place', $place);
        $schedule->setRelation('member', null);
        $schedule->setRelation('status', null);

        $html = $this->tela($this->recepcao(), 'schedule.list', [], 'location.schedule.index', [
            'schedules' => new LengthAwarePaginator([$schedule], 1, 25, 1, ['path' => route('schedule.list')]),
            'places' => collect([$place]),
            'statuses' => collect([(new Status)->forceFill(['id' => 3, 'portuguese' => 'Pendente'])]),
        ], ['member' => 'ana', 'status_id' => '3']);

        $this->assertStringContainsString('name="member"', $html);
        $this->assertStringContainsString('value="ana"', $html);
        $this->assertMatchesRegularExpression('#<option value="3"\s+selected#', $html);
        $this->assertStringContainsString('Limpar', $html);
        $this->assertStringContainsString(route('schedule.show', 90), $html);
        $this->assertStringContainsString('bg-warn-soft', $html);
    }

    public function test_pagamentos_com_busca_e_metodo_por_extenso(): void
    {
        $payment = (new SchedulePayment)->forceFill([
            'id' => 12, 'status_id' => 1, 'payment_method' => 'credit_card', 'paid_amount' => 80, 'refunded_amount' => 0,
            'paid_at' => '2026-10-01 09:30:00',
        ]);
        $payment->setRelation('schedules', collect());
        $payment->setRelation('status', (new Status)->forceFill(['id' => 1, 'portuguese' => 'Pago']));

        $html = $this->tela($this->recepcao(), 'payment.index', [], 'payments.index', [
            'payments' => new LengthAwarePaginator([$payment], 1, 25, 1, ['path' => route('payment.index')]),
            'statuses' => collect(),
        ]);

        $this->assertStringContainsString('name="member"', $html);
        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('Cartão de crédito', $html);
        $this->assertStringContainsString(route('payment.show', 12), $html);
    }

    public function test_modalidades_em_cartoes_com_busca(): void
    {
        $group = (new PlaceGroup)->forceFill(['id' => 3, 'name' => 'Tênis', 'category' => 'esporte', 'image_horizontal' => null]);
        $group->setRelation('places', collect([['price' => 80], ['price' => 60]]));

        $html = $this->tela($this->recepcao(), 'place-group.index', [], 'location.placeGroup.index', [
            'groups' => collect([$group]),
        ]);

        $this->assertStringContainsString('id="modalidades"', $html);
        $this->assertStringContainsString('a partir de R$ 60,00', $html);
        $this->assertStringContainsString(route('place-group.show', 3), $html);
        $this->assertStringNotContainsString('placehold.co', $html);
        $this->assertStringNotContainsString('pagination.js', $html);
    }

    public function test_cartao_de_torneio_edita_o_torneio_e_nao_um_local(): void
    {
        $this->requisicaoEm('place-group.index');
        $tournament = new \App\Models\Tournament\Tournament;
        $tournament->forceFill(['id' => 4, 'title' => 'Copa de Primavera', 'price' => 50, 'status_id' => 1, 'image' => null]);

        $html = (string) $this->view('location.placeGroup.partials.tournament-card', ['tournament' => $tournament]);

        $this->assertStringContainsString(route('tournaments.edit', 4), $html);
        $this->assertStringContainsString(route('tournaments.destroy', 4), $html);
        $this->assertStringNotContainsString(route('place-group.editPlace', 4), $html);
    }
}
