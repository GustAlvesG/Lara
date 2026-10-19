<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Member;
use App\Models\Place;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Baixa manual de pagamento nas telas de quadra: o detalhe do agendamento
 * oferece a baixa a quem tem a permissão, só para o que ainda não foi pago, e
 * o pagamento manual não oferece consulta nem estorno na Rede. Sem banco.
 */
class BaixaManualScreensTest extends TestCase
{
    use RendersScreens;

    private function schedule(int $id, int $status, ?SchedulePayment $payment = null, string $start = '2026-10-20 18:00:00'): Schedule
    {
        $schedule = new Schedule(['status_id' => $status, 'price' => 80, 'start_schedule' => $start, 'end_schedule' => Carbon::parse($start)->addHour()]);
        $schedule->id = $id;
        $schedule->schedule_payment_id = $payment?->id;
        $schedule->created_by_user = 9;
        $schedule->created_at = Carbon::parse('2026-10-19 09:00');
        $schedule->updated_at = $schedule->created_at;

        $member = new Member();
        $member->setRawAttributes(['name' => 'Ana Sócia', 'title' => '1001', 'email' => 'ana@teste.local']);

        $schedule->setRelation('member', $member);
        $schedule->setRelation('place', new Place(['name' => 'Quadra 1']));
        $schedule->setRelation('schedulePayment', $payment);
        $schedule->setRelation('socialBooking', null);
        foreach (['creator', 'editor', 'canceller', 'status'] as $relation) {
            $schedule->setRelation($relation, null);
        }

        return $schedule;
    }

    private function show($user, Schedule $schedule, array $others = []): string
    {
        return $this->tela($user, 'schedule.show', ['id' => $schedule->id], 'location.schedule.show', [
            'data' => ['schedule' => $schedule, 'other_schedules' => collect([$schedule, ...$others])],
        ]);
    }

    private function manualPayment(): SchedulePayment
    {
        $payment = new SchedulePayment(['payment_method' => 'manual_cash', 'paid_amount' => 80, 'paid_at' => '2026-10-19 09:30', 'status_id' => 1]);
        $payment->forceFill(['id' => 40, 'is_manual' => true, 'manual_notes' => 'Recibo 12', 'refunded_amount' => 0, 'created_at' => '2026-10-19 09:31']);
        $payment->setRelation('recorder', new User(['name' => 'Joana Caixa']));
        $payment->setRelation('refunder', null);
        $payment->setRelation('status', new Status(['portuguese' => 'confirmado']));
        $payment->setRelation('schedules', collect());

        return $payment;
    }

    public function test_agendamento_sem_pagamento_oferece_a_baixa_com_os_do_mesmo_dia(): void
    {
        $user = $this->usuario(new UserAccess([P::RESERVAS_AGENDAMENTOS, P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL]));
        $html = $this->show($user, $this->schedule(10, 1), [
            $this->schedule(11, 3, start: '2026-10-20 19:00:00'),
            $this->schedule(12, 1, $this->manualPayment(), '2026-10-20 20:00:00'),
        ]);

        $this->assertStringContainsString(route('payment.manual'), $html);
        $this->assertStringContainsString('Baixa manual de pagamento', $html);
        $this->assertStringContainsString('name="schedule_ids[]" value="10"', $html);
        $this->assertStringContainsString('name="schedule_ids[]" value="11"', $html);
        // O que já tem pagamento não entra.
        $this->assertStringNotContainsString('name="schedule_ids[]" value="12"', $html);
        $this->assertStringContainsString('value="manual_transfer"', $html);
    }

    public function test_sem_a_permissao_nao_ha_baixa(): void
    {
        $user = $this->usuario(new UserAccess([P::RESERVAS_AGENDAMENTOS]));

        $this->assertStringNotContainsString(route('payment.manual'), $this->show($user, $this->schedule(10, 1)));
    }

    public function test_agendamento_pago_ou_cancelado_nao_recebe_baixa(): void
    {
        $user = $this->usuario(new UserAccess([P::RESERVAS_AGENDAMENTOS, P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL]));

        $this->assertStringNotContainsString(route('payment.manual'), $this->show($user, $this->schedule(10, 1, $this->manualPayment())));
        $this->assertStringNotContainsString(route('payment.manual'), $this->show($user, $this->schedule(10, 0)));
    }

    public function test_pago_por_baixa_manual_nao_oferece_estorno_no_cancelamento(): void
    {
        $user = $this->usuario(new UserAccess([P::RESERVAS_AGENDAMENTOS]));
        $schedule = $this->schedule(10, 1, $this->manualPayment());
        $schedule->created_by_user = null; // via site

        $this->assertStringNotContainsString('name="refund_payment"', $this->show($user, $schedule));
    }

    public function test_detalhe_do_pagamento_manual_sem_rede(): void
    {
        $user = $this->usuario(new UserAccess([P::RESERVAS_PAGAMENTOS, P::RESERVAS_PAGAMENTOS_ESTORNAR]));
        $html = $this->tela($user, 'payment.show', ['schedulePayment' => 40], 'payments.show', [
            'schedulePayment' => $this->manualPayment(),
            'redeSummary' => null,
            'redeError' => null,
        ]);

        $this->assertStringContainsString('Baixa manual — pago fora da plataforma', $html);
        $this->assertStringContainsString('Registrada por Joana Caixa em 19/10/2026 09:31', $html);
        $this->assertStringContainsString('Recibo 12', $html);
        $this->assertStringContainsString('Dinheiro', $html);
        $this->assertStringNotContainsString('Consultar transação na Rede', $html);
        $this->assertStringNotContainsString(route('payment.refund', 40), $html);
    }
}
