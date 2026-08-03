<?php

namespace Tests\Unit;

use App\Console\Commands\ExpirePendingSchedules;
use App\Mail\ContactMail;
use App\Models\Member;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Services\SchedulesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * E-mails de agendamento: qual sai, para quem e com quais números.
 *
 * O bug que originou estes testes: no fluxo do app externo a reserva nasce
 * pendente (status 3) e só vira paga no endpoint de pagamento — como o e-mail
 * era decidido apenas na criação, o de confirmação nunca era enviado. Aqui a
 * decisão passa a ser tomada a partir do status gravado no agendamento.
 *
 * Sem banco: o model Member está preso à conexão `mysql` e a suíte roda em
 * SQLite, então os models são montados em memória com as relações já definidas
 * (o service usa loadMissing, que não consulta o que já está carregado).
 */
class ScheduleNotificationTest extends TestCase
{
    private function member(?string $email = 'socio@exemplo.com', int $id = 7, string $name = 'Maria Souza'): Member
    {
        return (new Member)->forceFill([
            'id' => $id,
            'name' => $name,
            'title' => '12345',
            'email' => $email,
        ]);
    }

    /**
     * @param  array<string, mixed>  $columns   colunas extras do agendamento
     */
    private function schedule(
        int $id,
        int $statusId,
        string $start,
        string $end,
        float $price,
        ?Member $member = null,
        array $columns = [],
        ?SchedulePayment $payment = null
    ): Schedule {
        $group = (new PlaceGroup)->forceFill(['id' => 2, 'name' => 'Quadras']);
        $place = (new Place)->forceFill(['id' => 3, 'name' => 'Quadra de Tênis 1']);
        $place->setRelation('group', $group);

        $member = $member ?: $this->member();

        $schedule = (new Schedule)->forceFill(array_merge([
            'id' => $id,
            'place_id' => 3,
            'member_id' => $member->id,
            'status_id' => $statusId,
            'start_schedule' => $start,
            'end_schedule' => $end,
            'price' => $price,
            'created_at' => Carbon::parse('2026-08-03 14:20:00'),
        ], $columns));

        $schedule->setRelation('place', $place);
        $schedule->setRelation('member', $member);
        $schedule->setRelation('schedulePayment', $payment);

        return $schedule;
    }

    private function payment(): SchedulePayment
    {
        return (new SchedulePayment)->forceFill([
            'id' => 55,
            'payment_method' => 'credit',
            'paid_amount' => 120.00,
            'paid_at' => Carbon::parse('2026-08-03 14:25:00'),
            'payment_integration_id' => '30012345678901',
            'status_id' => 1,
        ]);
    }

    /** @return array{0: ContactMail, 1: string} mailable e corpo renderizado */
    private function captureMail(): array
    {
        $mailable = Mail::queued(ContactMail::class)->first() ?: Mail::sent(ContactMail::class)->first();
        $this->assertNotNull($mailable, 'Nenhum e-mail de agendamento foi despachado.');

        return [$mailable, $mailable->render()];
    }

    public function test_reserva_pendente_recebe_o_email_de_pendencia_com_o_prazo_real(): void
    {
        Mail::fake();

        $sent = (new SchedulesService)->notifyScheduleStatus([
            $this->schedule(101, 3, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00),
        ]);

        $this->assertTrue($sent);

        [$mailable, $body] = $this->captureMail();

        $this->assertSame('schedule.pending', $mailable->data['type']);
        $this->assertTrue($mailable->hasTo('socio@exemplo.com'));
        $this->assertStringContainsString('aguardando pagamento', $mailable->data['subject']);

        // O prazo informado tem de ser o mesmo que a rotina de expiração aplica.
        $this->assertSame(ExpirePendingSchedules::HOLD_MINUTES, $mailable->data['hold_minutes']);
        $this->assertStringContainsString('03/08/2026 14:50', $body);
        $this->assertStringContainsString('R$ 60,00', $body);
    }

    public function test_reserva_paga_recebe_o_email_de_confirmacao_com_os_dados_do_pagamento(): void
    {
        Mail::fake();

        $schedules = [
            $this->schedule(101, 1, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00),
            $this->schedule(102, 1, '2026-08-05 20:00:00', '2026-08-05 21:00:00', 60.00),
        ];

        $this->assertTrue((new SchedulesService)->notifyScheduleStatus($schedules, $this->payment()));

        [$mailable, $body] = $this->captureMail();

        $this->assertSame('schedule.confirm', $mailable->data['type']);
        $this->assertStringContainsString('Agendamento confirmado', $mailable->data['subject']);

        // Total é a soma do que foi gravado — antes o e-mail multiplicava um
        // preço vindo da requisição, que no fluxo externo nem é enviado.
        $this->assertSame('120,00', $mailable->data['total']);
        $this->assertStringContainsString('R$ 120,00', $body);
        $this->assertStringContainsString('19:00 às 20:00', $body);
        $this->assertStringContainsString('20:00 às 21:00', $body);
        $this->assertStringContainsString('Cartão de crédito', $body);
        $this->assertStringContainsString('30012345678901', $body);
    }

    public function test_lote_misto_gera_um_email_por_situacao(): void
    {
        Mail::fake();

        (new SchedulesService)->notifyScheduleStatus([
            $this->schedule(101, 1, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00),
            $this->schedule(102, 3, '2026-08-05 20:00:00', '2026-08-05 21:00:00', 60.00),
        ]);

        Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->data['type'] === 'schedule.confirm');
        Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->data['type'] === 'schedule.pending');
        Mail::assertQueuedCount(2);
    }

    public function test_cancelamento_pelo_painel_informa_motivo_e_estorno(): void
    {
        Mail::fake();

        $cancelado = $this->schedule(
            101,
            0,
            '2026-08-05 19:00:00',
            '2026-08-05 20:00:00',
            60.00,
            null,
            [
                'schedule_payment_id' => 55,
                'cancel_reason' => 'Manutenção emergencial na iluminação da quadra.',
                'cancelled_at' => Carbon::parse('2026-08-04 09:30:00'),
            ],
            $this->payment()
        );

        $this->assertTrue((new SchedulesService)->notifyScheduleStatus([$cancelado], null, [
            'refund_requested' => true,
            'refund_deltas' => [55 => 60.00],
        ]));

        [$mailable, $body] = $this->captureMail();

        $this->assertSame('schedule.cancel', $mailable->data['type']);
        $this->assertStringContainsString('Agendamento cancelado', $mailable->data['subject']);
        $this->assertStringContainsString('Manutenção emergencial na iluminação da quadra.', $body);
        $this->assertStringContainsString('04/08/2026 09:30', $body);
        $this->assertStringContainsString('Estorno solicitado: R$ 60,00', $body);
    }

    public function test_cancelamento_sem_estorno_nao_promete_devolucao(): void
    {
        Mail::fake();

        // Estorno pedido, mas a chamada ao gateway não devolveu nada (falhou):
        // o e-mail sai mesmo assim, sem afirmar que houve devolução.
        $cancelado = $this->schedule(
            101,
            0,
            '2026-08-05 19:00:00',
            '2026-08-05 20:00:00',
            60.00,
            null,
            ['schedule_payment_id' => 55, 'cancel_reason' => 'Evento do clube.'],
            $this->payment()
        );

        (new SchedulesService)->notifyScheduleStatus([$cancelado], null, [
            'refund_requested' => true,
            'refund_deltas' => [],
        ]);

        [, $body] = $this->captureMail();

        $this->assertStringNotContainsString('Estorno solicitado', $body);
        $this->assertStringContainsString('procure a secretaria', $body);
    }

    public function test_cancelamento_em_lote_separa_os_socios(): void
    {
        Mail::fake();

        $outro = $this->member('outro@exemplo.com', id: 9, name: 'João Lima');

        (new SchedulesService)->notifyScheduleStatus([
            $this->schedule(101, 0, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00),
            $this->schedule(102, 0, '2026-08-05 20:00:00', '2026-08-05 21:00:00', 60.00, $outro),
        ]);

        Mail::assertQueuedCount(2);
        Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->hasTo('socio@exemplo.com')
            && $mail->data['schedule_ids'] === [101]);
        Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->hasTo('outro@exemplo.com')
            && $mail->data['schedule_ids'] === [102]);
    }

    public function test_status_sem_interesse_para_o_socio_nao_gera_email(): void
    {
        Mail::fake();

        // 4 = expirado: o horário só volta para a grade, não vira e-mail.
        $this->assertFalse((new SchedulesService)->notifyScheduleStatus([
            $this->schedule(101, 4, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00),
        ]));

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_socio_sem_email_nao_derruba_o_fluxo(): void
    {
        Mail::fake();

        $schedule = $this->schedule(101, 3, '2026-08-05 19:00:00', '2026-08-05 20:00:00', 60.00, $this->member(null));

        $this->assertFalse((new SchedulesService)->notifyScheduleStatus([$schedule]));

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }
}
