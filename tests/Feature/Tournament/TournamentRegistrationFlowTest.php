<?php

namespace Tests\Feature\Tournament;

use App\Exceptions\TournamentException;
use App\Mail\ContactMail;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentPayment;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\User;
use App\Services\Tournament\TournamentRegistrationService;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F3: inscrição individual, pagamento eRede, baixa manual,
 * desistência e cancelamento com estorno — as regras do
 * TournamentRegistrationService sobre o banco de verdade do módulo, com o
 * MultiClubes e a eRede em memória.
 */
class TournamentRegistrationFlowTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private TournamentRegistrationService $service;
    private Tournament $tournament;
    private TournamentCategory $duplas;
    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTournamentSchema();
        $this->fakeIntegrations();
        $this->service = $this->app->make(TournamentRegistrationService::class);

        $this->tournament = $this->tournament();
        $this->duplas = $this->category($this->tournament);
        $this->organizer = $this->makeUser('Pessoa do Esporte');
    }

    private function registerAna(?array $categoryIds = null, ?array $override = null)
    {
        $ana = $this->member('39053344705', 'Ana Souza', '1001');

        return [$ana, $this->service->register($this->tournament, $ana, $this->service->profileFor($ana), $categoryIds ?? [$this->duplas->id], $override)];
    }

    private function assertRefused(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Esperava a recusa {$reason}.");
        } catch (TournamentException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
        }
    }

    private function payment(array $overrides = []): array
    {
        return array_merge(['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-1'], $overrides);
    }

    public function test_inscricao_copia_os_dados_do_multiclubes_e_aguarda_pagamento(): void
    {
        [$ana, $created] = $this->registerAna();

        $registration = $created->first();
        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registration->status);
        $this->assertSame('80.00', $registration->amount);
        $this->assertSame('1001', $registration->title);
        $this->assertSame(Template::GENDER_FEMALE, $registration->gender);
        $this->assertSame('ana@mc.local', $registration->email);
        $this->assertSame($ana->id, $registration->member_id);

        Mail::assertQueued(ContactMail::class, fn ($mail) => $mail->type === 'tournament.registered' && $mail->hasTo('ana@mc.local'));
    }

    public function test_quem_nao_e_socio_ativo_no_multiclubes_e_recusado(): void
    {
        $ana = $this->member('39053344705', 'Ana Souza', '1001');
        $this->mcProfiles = [];

        $this->assertRefused(TournamentException::MEMBER_NOT_FOUND, fn () => $this->service->profileFor($ana));
    }

    public function test_sexo_e_idade_da_categoria_e_a_liberacao_pelo_organizador(): void
    {
        $masculina = $this->category($this->tournament, ['name' => 'Masculina', 'gender' => Template::GENDER_MALE]);

        $this->assertRefused(TournamentException::INELIGIBLE, fn () => $this->registerAna([$masculina->id]));
        $this->assertSame(0, Registration::count());

        $bruno = $this->member('86288366757', 'Bruno Lima', '1002', Template::GENDER_MALE);
        $created = $this->service->register($this->tournament, $bruno, $this->service->profileFor($bruno), [$masculina->id]);
        $this->assertNull($created->first()->eligibility_override_by);

        // O organizador libera com justificativa.
        $ana = \App\Models\Member::where('cpf', '39053344705')->first();
        $released = $this->service->register($this->tournament, $ana, $this->service->profileFor($ana), [$masculina->id], [$this->organizer, 'Cadastro com sexo errado no MultiClubes']);
        $this->assertSame($this->organizer->id, $released->first()->eligibility_override_by);
        $this->assertSame('Cadastro com sexo errado no MultiClubes', $released->first()->eligibility_override_reason);
    }

    public function test_varias_categorias_entram_juntas_ou_nenhuma(): void
    {
        $quarenta = $this->category($this->tournament, ['name' => '40+', 'min_age' => 40]);

        // Ana nasceu em 1990: a segunda categoria recusa e a primeira também não entra.
        $this->assertRefused(TournamentException::INELIGIBLE, fn () => $this->registerAna([$this->duplas->id, $quarenta->id]));
        $this->assertSame(0, Registration::count());
    }

    public function test_fora_do_periodo_de_inscricao_nao_inscreve(): void
    {
        $this->tournament->update(['registration_ends_at' => now()->subMinute()]);

        $this->assertRefused(TournamentException::REGISTRATION_CLOSED, fn () => $this->registerAna());

        $this->tournament->update(['registration_ends_at' => now()->addDay(), 'status' => Tournament::STATUS_DRAFT]);
        $this->assertRefused(TournamentException::REGISTRATION_CLOSED, fn () => $this->registerAna());
    }

    public function test_uma_inscricao_ativa_por_categoria_e_desistir_libera(): void
    {
        [$ana, $created] = $this->registerAna();

        $this->assertRefused(TournamentException::ALREADY_REGISTERED, fn () => $this->service->register($this->tournament, $ana, $this->service->profileFor($ana), [$this->duplas->id]));

        $this->service->withdraw($created->first(), $ana);
        $this->assertSame(Registration::STATUS_WITHDRAWN, $created->first()->fresh()->status);

        $again = $this->service->register($this->tournament, $ana, $this->service->profileFor($ana), [$this->duplas->id]);
        $this->assertCount(1, $again);
    }

    public function test_pagamento_confirma_e_o_mesmo_tid_nao_paga_duas_vezes(): void
    {
        [$ana, $created] = $this->registerAna();
        $ids = $created->pluck('id')->all();

        $this->assertRefused(TournamentException::PAYMENT_MISMATCH, fn () => $this->service->pay($ana, $ids, $this->payment(['paid_amount' => 50])));

        $first = $this->service->pay($ana, $ids, $this->payment());
        $this->assertTrue($first['created']);
        $this->assertSame(Registration::STATUS_PAID, $created->first()->fresh()->status);
        Mail::assertQueued(ContactMail::class, fn ($mail) => $mail->type === 'tournament.paid');

        $retry = $this->service->pay($ana, $ids, $this->payment());
        $this->assertFalse($retry['created']);
        $this->assertSame($first['payment']->id, $retry['payment']->id);
        $this->assertSame(1, TournamentPayment::count());
    }

    public function test_pagamento_de_outra_pessoa_e_recusado(): void
    {
        [, $created] = $this->registerAna();
        $bruno = $this->member('86288366757', 'Bruno Lima', '1002', Template::GENDER_MALE);

        $this->assertRefused(TournamentException::NOT_OWNER, fn () => $this->service->pay($bruno, $created->pluck('id')->all(), $this->payment()));
    }

    public function test_vaga_e_de_quem_paga_primeiro(): void
    {
        $this->duplas->update(['max_registrations' => 1]);

        [$ana, $anaRegs] = $this->registerAna();
        $bruno = $this->member('86288366757', 'Bruno Lima', '1002', Template::GENDER_MALE);
        $brunoRegs = $this->service->register($this->tournament, $bruno, $this->service->profileFor($bruno), [$this->duplas->id]);

        $this->service->pay($bruno, $brunoRegs->pluck('id')->all(), $this->payment(['payment_integration_id' => 'TID-B']));

        $this->assertRefused(TournamentException::CATEGORY_FULL, fn () => $this->service->pay($ana, $anaRegs->pluck('id')->all(), $this->payment()));

        $carla = $this->member('52998224725', 'Carla Dias', '1003');
        $this->assertRefused(TournamentException::CATEGORY_FULL, fn () => $this->service->register($this->tournament, $carla, $this->service->profileFor($carla), [$this->duplas->id]));
    }

    public function test_categoria_gratuita_ja_nasce_paga(): void
    {
        $this->duplas->update(['price' => 0]);

        [, $created] = $this->registerAna();

        $this->assertSame(Registration::STATUS_PAID, $created->first()->status);
        $this->assertNotNull($created->first()->paid_at);
    }

    public function test_desistencia_pelo_site_so_antes_de_pagar(): void
    {
        [$ana, $created] = $this->registerAna();
        $this->service->pay($ana, $created->pluck('id')->all(), $this->payment());

        $this->assertRefused(TournamentException::PAID_CONTACT_ORGANIZER, fn () => $this->service->withdraw($created->first()->fresh(), $ana));
    }

    public function test_organizador_cancela_com_estorno_da_inscricao(): void
    {
        $simples = $this->category($this->tournament, ['name' => 'Simples', 'team_size' => 1, 'price' => 50]);
        [$ana, $created] = $this->registerAna([$this->duplas->id, $simples->id]);
        $this->service->pay($ana, $created->pluck('id')->all(), $this->payment(['paid_amount' => 130]));

        $registration = $created->firstWhere('tournament_category_id', $simples->id)->fresh();
        $refunded = $this->service->cancel($registration, $this->organizer, 'Lesão', refund: true);

        $this->assertSame(50.0, $refunded);
        $this->assertSame([['tid' => 'TID-1', 'amount' => 50.0]], $this->redeRefunds);
        $this->assertSame(Registration::STATUS_CANCELLED, $registration->fresh()->status);
        $this->assertSame('Lesão', $registration->fresh()->cancel_reason);

        $payment = TournamentPayment::first();
        $this->assertSame('50.00', $payment->refunded_amount);
        $this->assertSame(TournamentPayment::STATUS_PARTIALLY_REFUNDED, $payment->status);
        Mail::assertQueued(ContactMail::class, fn ($mail) => $mail->type === 'tournament.cancelled' && $mail->data['refunded'] === '50,00');
    }

    public function test_email_de_inscricao_renderiza_com_prazo_e_categorias(): void
    {
        [, $created] = $this->registerAna();

        $data = $this->app->make(\App\Services\Tournament\TournamentNotifier::class)->build($created, 'tournament.registered');
        $html = view('emails.tournament.status', ['data' => $data])->render();

        $this->assertStringContainsString('Inscrição recebida', $html);
        $this->assertStringContainsString('Copa de Verão', $html);
        $this->assertStringContainsString('Duplas Livre', $html);
        $this->assertStringContainsString('R$ 80,00', $html);
        $this->assertStringContainsString('Pague até ' . $this->tournament->registration_ends_at->format('d/m/Y H:i'), $html);
    }

    public function test_baixa_manual_paga_e_nao_tem_estorno_pelo_lara(): void
    {
        [, $created] = $this->registerAna();

        $payment = $this->service->recordManual($created->pluck('id')->all(), ['payment_method' => 'manual_pix', 'manual_notes' => 'Pix E123'], $this->organizer);

        $this->assertTrue($payment->is_manual);
        $this->assertSame($this->organizer->id, $payment->recorded_by);
        $this->assertSame('80.00', $payment->paid_amount);
        $this->assertSame(Registration::STATUS_PAID, $created->first()->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->service->cancel($created->first()->fresh(), $this->organizer, 'Desistiu', refund: true);
    }
}
