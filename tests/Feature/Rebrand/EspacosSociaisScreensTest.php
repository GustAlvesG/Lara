<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Member;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Models\Social\SocialBookingGuest;
use App\Models\Social\SocialBookingItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Telas de Reservas › Espaços Sociais: lista, nova pré-reserva, detalhe com as
 * ações de cada estágio e o catálogo de bebidas. Models não salvos, sem banco;
 * usuário mock. Também confere a porta (permissão) de cada rota.
 */
class EspacosSociaisScreensTest extends TestCase
{
    use RendersScreens;

    private function atendente()
    {
        return $this->usuario(new UserAccess([P::RESERVAS_SOCIAIS]));
    }

    private function secretaria()
    {
        return $this->usuario(new UserAccess([P::RESERVAS_SOCIAIS, P::RESERVAS_SOCIAIS_APROVAR, P::RESERVAS_CONFIGURAR, P::RESERVAS_PAGAMENTOS_ESTORNAR]));
    }

    private function booking(string $stage = SocialBooking::STAGE_REQUESTED): SocialBooking
    {
        $group = new PlaceGroup(['name' => 'Churrasqueiras', 'category' => 'social', 'social_payment_days' => 7]);
        $place = new Place(['name' => 'Churrasqueira 21', 'price' => 350, 'max_guests' => 15, 'extra_guests' => 5, 'extra_guest_price' => 15]);
        $place->setRelation('group', $group);

        $booking = new SocialBooking([
            'stage' => $stage, 'event_date' => '2026-11-14', 'event_type' => 'Aniversário', 'expected_guests' => 40,
            'base_price' => 350, 'extra_guests' => 2, 'extra_guest_price' => 15, 'extras_total' => 30,
            'items_total' => 60, 'discount' => 0, 'total' => 440,
            'payment_due_at' => $stage === SocialBooking::STAGE_AWAITING_PAYMENT ? '2026-10-21 23:59:59' : null,
        ]);
        $booking->id = 5;
        $booking->schedule_id = 77;
        $booking->created_at = Carbon::parse('2026-10-14 10:00');

        $member = new Member();
        $member->setRawAttributes(['name' => 'Ana Sócia', 'title' => '1001', 'email' => 'ana@teste.local']);

        $schedule = new Schedule();
        $schedule->setRelation('schedulePayment', null);

        $item = new SocialBookingItem(['social_beverage_id' => 3, 'name' => 'Água (fardo)', 'mv_code' => 'MV-123', 'unit_price' => 30, 'quantity' => 2, 'subtotal' => 60]);
        $guest = new SocialBookingGuest(['name' => 'Carlos Convidado', 'cpf' => '52998224725']);
        $guest->setRelation('oneOffAccess', null);

        $booking->setRelation('place', $place);
        $booking->setRelation('member', $member);
        $booking->setRelation('schedule', $schedule);
        $booking->setRelation('items', collect([$item]));
        $booking->setRelation('guests', collect([$guest]));
        foreach (['approver', 'contractChecker', 'refuser', 'canceller', 'creator'] as $relation) {
            $booking->setRelation($relation, null);
        }

        return $booking;
    }

    private function beverages()
    {
        $water = new SocialBeverage(['name' => 'Água (fardo)', 'unit' => 'fardo 12 un.', 'mv_code' => 'MV-123', 'price' => 30, 'active' => true]);
        $water->id = 3;

        return collect([$water]);
    }

    public function test_lista_mostra_a_fila_e_o_estagio(): void
    {
        $html = $this->tela($this->secretaria(), 'social-booking.index', [], 'location.social.index', [
            'bookings' => new LengthAwarePaginator([$this->booking()], 1, 25),
            'places' => collect(),
            'stages' => SocialBooking::STAGE_LABELS,
            'counts' => collect(['requested' => 3, 'awaiting_payment' => 1]),
            'awaitingContact' => 2,
            'overdueContact' => 1,
        ]);

        $this->assertStringContainsString('1 com contato atrasado', $html);
        $this->assertStringContainsString('2 aguardando contato', $html);

        $this->assertStringContainsString('3 em análise', $html);
        $this->assertStringContainsString('1 aguardando pagamento', $html);
        $this->assertStringContainsString('Em análise', $html);
        $this->assertStringContainsString('R$ 440,00', $html);
        $this->assertStringContainsString(route('social-booking.beverages.index'), $html);
        // A aba nova aparece no menu de Reservas.
        $this->assertStringContainsString('Espaços Sociais', $html);
    }

    public function test_quem_so_atende_nao_ve_o_catalogo_de_bebidas(): void
    {
        $html = $this->tela($this->atendente(), 'social-booking.index', [], 'location.social.index', [
            'bookings' => new LengthAwarePaginator([], 0, 25),
            'places' => collect(),
            'stages' => SocialBooking::STAGE_LABELS,
            'counts' => collect(),
            'awaitingContact' => 0,
            'overdueContact' => 0,
        ]);

        $this->assertStringNotContainsString(route('social-booking.beverages.index'), $html);
    }

    public function test_nova_pre_reserva_tem_calendario_extras_bebidas_e_convidados(): void
    {
        $group = new PlaceGroup(['name' => 'Churrasqueiras', 'category' => 'social']);
        $place = new Place(['name' => 'Churrasqueira 21', 'price' => 350, 'max_guests' => 15, 'extra_guests' => 5, 'extra_guest_price' => 15, 'capacity' => 40]);
        $place->id = 7;
        $place->setRelation('group', $group);

        $html = $this->tela($this->atendente(), 'social-booking.create', [], 'location.social.create', [
            'places' => collect([$place]),
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringContainsString(route('social-booking.availability'), $html);
        $this->assertStringContainsString('data-max-guests="15"', $html);
        $this->assertStringContainsString('data-extra-price="15"', $html);
        $this->assertStringContainsString('name="extra_guests"', $html);
        $this->assertStringContainsString('name="items[0][quantity]"', $html);
        $this->assertStringContainsString('id="guest-template"', $html);
    }

    public function test_detalhe_em_analise_oferece_contrato_aprovar_e_recusar(): void
    {
        $html = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(),
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringContainsString(route('social-booking.contract', 5), $html);
        $this->assertStringContainsString(route('social-booking.approve', 5), $html);
        $this->assertStringContainsString(route('social-booking.refuse', 5), $html);
        $this->assertStringContainsString(route('social-booking.cancel', 5), $html);
        $this->assertStringContainsString('MV MV-123', $html);
        $this->assertStringContainsString('Convidados não sócios (1 de 17)', $html);
        // Sem contrato, o botão de aprovar vem desabilitado.
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*Aprovar e liberar pagamento/', $html);
    }

    public function test_contrato_ok_tira_a_edicao_da_pre_reserva(): void
    {
        $semContrato = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(),
            'beverages' => $this->beverages(),
        ]);
        $this->assertStringContainsString(route('social-booking.details', 5), $semContrato);

        $booking = $this->booking();
        $booking->contract_ok_at = Carbon::parse('2026-10-15 09:00');
        $comContrato = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $booking,
            'beverages' => $this->beverages(),
        ]);
        $this->assertStringNotContainsString(route('social-booking.details', 5), $comContrato);
    }

    public function test_detalhe_mostra_horario_mesas_e_prazo_de_contato(): void
    {
        $booking = $this->booking();
        $booking->forceFill(['start_time' => '20:00:00', 'end_time' => '02:00:00', 'duration_hours' => 6, 'base_tables' => 15, 'base_chairs' => 60, 'contact_due_at' => '2026-10-10 23:59:59']);
        $booking->setRelation('contactedBy', null);

        $html = $this->tela($this->atendente(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $booking,
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringContainsString('20:00 às 02:00 (dia seguinte)', $html);
        $this->assertStringContainsString('15 mesa(s) · 60 cadeira(s)', $html);
        $this->assertStringContainsString('Atrasado: o prazo era 10/10/2026 23:59', $html);
        $this->assertStringContainsString(route('social-booking.contact', 5), $html);
    }

    public function test_quem_so_atende_nao_decide(): void
    {
        $html = $this->tela($this->atendente(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(),
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringNotContainsString(route('social-booking.approve', 5), $html);
        $this->assertStringNotContainsString(route('social-booking.cancel', 5), $html);
        // Mas cuida da lista de convidados.
        $this->assertStringContainsString(route('social-booking.guests', 5), $html);
    }

    public function test_detalhe_aguardando_pagamento_mostra_o_prazo_e_nao_reaprova(): void
    {
        $html = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(SocialBooking::STAGE_AWAITING_PAYMENT),
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringContainsString('21/10/2026 23:59', $html);
        $this->assertStringNotContainsString(route('social-booking.approve', 5), $html);
        $this->assertStringContainsString(route('social-booking.cancel', 5), $html);
    }

    public function test_catalogo_de_bebidas_tem_codigo_mv(): void
    {
        $html = $this->tela($this->secretaria(), 'social-booking.beverages.index', [], 'location.social.beverages', [
            'beverages' => $this->beverages(),
            'editing' => null,
        ]);

        $this->assertStringContainsString('Código MV', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertStringContainsString('name="chairs_per_unit"', $html);
        $this->assertStringContainsString('name="max_quantity"', $html);
        $this->assertStringContainsString('name="mv_code"', $html);
        $this->assertStringContainsString('MV-123', $html);
    }

    public function test_formulario_do_espaco_social_troca_luz_por_convidados_e_conflitos(): void
    {
        $group = new PlaceGroup(['name' => 'Churrasqueiras', 'category' => 'social']);
        $group->id = 4;
        $court = new Place(['name' => 'Quadra 1']);
        $court->id = 8;
        $court->setRelation('group', new PlaceGroup(['name' => 'Tênis']));

        $html = $this->tela($this->secretaria(), 'place-group.createPlace', ['id' => 4], 'location.place.create', [
            'place_group' => $group,
            'rules' => [],
            'contactors' => collect(),
            'conflictOptions' => collect([$court]),
            'selectedConflicts' => [],
        ]);

        // Imagem por arquivo ou por link (imgur, postimg).
        $this->assertStringContainsString('type="file" name="image"', $html);
        $this->assertStringContainsString('name="image_url"', $html);

        foreach (['name="base_tables"','name="base_chairs"', 'name="event_duration_hours"', 'name="event_earliest_start"', 'name="event_latest_start"', 'name="capacity"', 'name="max_guests"', 'name="extra_guests"', 'name="extra_guest_price"', 'name="amenities"', 'name="conflicts[]"'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
        $this->assertStringContainsString('Tênis – Quadra 1', $html);
        $this->assertStringNotContainsString('name="contactor_id"', $html);
        $this->assertStringNotContainsString('name="self_service_lighting"', $html);
    }

    private function caixa()
    {
        return $this->usuario(new UserAccess([P::RESERVAS_SOCIAIS, P::RESERVAS_SOCIAIS_APROVAR, P::RESERVAS_PAGAMENTOS, P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL]));
    }

    private function createScreen($user): string
    {
        $group = new PlaceGroup(['name' => 'Churrasqueiras', 'category' => 'social']);
        $place = new Place(['name' => 'Churrasqueira 21', 'price' => 350, 'max_guests' => 15, 'extra_guests' => 5, 'extra_guest_price' => 15]);
        $place->id = 7;
        $place->setRelation('group', $group);

        return $this->tela($user, 'social-booking.create', [], 'location.social.create', [
            'places' => collect([$place]),
            'beverages' => $this->beverages(),
        ]);
    }

    public function test_balcao_pode_fechar_a_reserva_inteira_conforme_a_permissao(): void
    {
        $atendente = $this->createScreen($this->atendente());
        $this->assertStringNotContainsString('name="complete"', $atendente);

        // Quem aprova fecha (contrato + aprovação), mas sem baixa não registra pagamento.
        $secretaria = $this->createScreen($this->secretaria());
        $this->assertStringContainsString('name="complete"', $secretaria);
        $this->assertStringContainsString('name="contract_file"', $secretaria);
        $this->assertStringContainsString('name="discount"', $secretaria);
        $this->assertStringContainsString('enctype="multipart/form-data"', $secretaria);
        $this->assertStringNotContainsString('name="paid_now"', $secretaria);

        $caixa = $this->createScreen($this->caixa());
        $this->assertStringContainsString('name="paid_now"', $caixa);
        $this->assertStringContainsString('value="manual_pix"', $caixa);
    }

    public function test_aguardando_pagamento_oferece_a_baixa_manual_a_quem_pode(): void
    {
        $semBaixa = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(SocialBooking::STAGE_AWAITING_PAYMENT),
            'beverages' => $this->beverages(),
        ]);
        $this->assertStringNotContainsString(route('payment.manual'), $semBaixa);
        $this->assertStringContainsString('Aguardando o sócio pagar pelo site até 21/10/2026 23:59', $semBaixa);

        $comBaixa = $this->tela($this->caixa(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $this->booking(SocialBooking::STAGE_AWAITING_PAYMENT),
            'beverages' => $this->beverages(),
        ]);
        $this->assertStringContainsString(route('payment.manual'), $comBaixa);
        $this->assertStringContainsString('name="schedule_ids[]" value="77"', $comBaixa);
        $this->assertStringContainsString('name="amount" value="440.00"', $comBaixa);
    }

    public function test_reserva_paga_por_baixa_manual_nao_oferece_estorno_pela_rede(): void
    {
        $payment = new \App\Models\SchedulePayment(['payment_method' => 'manual_cash', 'paid_amount' => 440, 'paid_at' => '2026-10-19 09:00', 'status_id' => 1]);
        $payment->forceFill(['id' => 31, 'is_manual' => true, 'manual_notes' => 'Recibo 456', 'refunded_amount' => 0]);
        $payment->setRelation('recorder', new \App\Models\User(['name' => 'Joana Caixa']));

        $booking = $this->booking(SocialBooking::STAGE_CONFIRMED);
        $booking->schedule->setRelation('schedulePayment', $payment);

        $html = $this->tela($this->secretaria(), 'social-booking.show', ['booking' => 5], 'location.social.show', [
            'booking' => $booking,
            'beverages' => $this->beverages(),
        ]);

        $this->assertStringContainsString('Dinheiro', $html);
        $this->assertStringContainsString('baixa manual por Joana Caixa', $html);
        $this->assertStringContainsString('Recibo 456', $html);
        $this->assertStringContainsString('não há estorno pela Rede', $html);
        $this->assertStringNotContainsString('name="refund_amount"', $html);
    }

    public function test_formulario_do_grupo_aceita_arquivo_ou_link_e_mostra_a_imagem_atual(): void
    {
        $novo = $this->tela($this->secretaria(), 'place-group.create', [], 'location.placeGroup.create', []);
        foreach (['name="image_vertical"', 'name="image_vertical_url"', 'name="image_horizontal"', 'name="image_horizontal_url"'] as $field) {
            $this->assertStringContainsString($field, $novo);
        }
        $this->assertStringContainsString('Sem imagem', $novo);

        $group = new PlaceGroup(['name' => 'Churrasqueiras', 'category' => 'social', 'image_vertical' => 'vertical.jpg', 'image_horizontal' => 'https://i.imgur.com/abc1234.jpg']);
        $group->weekdays = []; // o controller entrega os ids, não a relação
        $this->actingAs($this->secretaria());
        $this->requisicaoEm('place-group.create');
        $form = (string) $this->view('location.placeGroup.partials.form', ['item' => $group]);

        $this->assertStringContainsString('src="' . asset('images/vertical.jpg') . '"', $form);
        $this->assertStringContainsString('src="https://i.imgur.com/abc1234.jpg"', $form);
        $this->assertStringContainsString('Atual: link externo', $form);
        $this->assertStringContainsString('Atual: arquivo enviado', $form);
    }

    public function test_cada_rota_tem_a_sua_porta(): void
    {
        $expected = [
            'payment.manual' => P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL,
            'social-booking.index' => P::RESERVAS_SOCIAIS,
            'social-booking.store' => P::RESERVAS_SOCIAIS,
            'social-booking.guests' => P::RESERVAS_SOCIAIS,
            'social-booking.guests.pdf' => P::RESERVAS_SOCIAIS,
            'social-booking.contract' => P::RESERVAS_SOCIAIS_APROVAR,
            'social-booking.approve' => P::RESERVAS_SOCIAIS_APROVAR,
            'social-booking.refuse' => P::RESERVAS_SOCIAIS_APROVAR,
            'social-booking.cancel' => P::RESERVAS_SOCIAIS_APROVAR,
            'social-booking.beverages.index' => P::RESERVAS_CONFIGURAR,
            'social-booking.beverages.store' => P::RESERVAS_CONFIGURAR,
        ];

        foreach ($expected as $name => $permission) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('can:' . $permission, $middleware, "{$name} deveria exigir {$permission}");
        }

        foreach (['api.social.bookings.store', 'api.social.bookings.show', 'api.social.limits'] as $name) {
            $this->assertContains('login_token', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }
}
