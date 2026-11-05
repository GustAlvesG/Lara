<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureContract;
use App\Models\SignatureDocument;
use App\Models\User;
use App\Notifications\ContractMovement;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractNotifier;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureStateMachine;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Contratos — vigência e notificações no navegador (05/11/2026, decisões do
 * usuário):
 *
 *  - vigência = meses a partir da assinatura (ou data fixa); o término é
 *    calculado quando todos assinam;
 *  - aviso a 60, 30 e 15 dias do fim, para quem redigiu e o setor, uma vez
 *    cada; aba "Vencendo" na lista;
 *  - notificações (sino/navegador): Gerência inteira no envio para
 *    validação; quem redigiu na devolução, aprovação e ajuste da Gerência;
 *    os dois na recusa, assinatura e cancelamento.
 */
class SignatureContractValidityNotificationsTest extends TestCase
{
    use CreatesContractSchema;
    use MocksSignatureUser;

    private Sector $compras;

    /** @var \Mockery\MockInterface&ContractNotifier */
    private $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

        $this->notifier = \Mockery::spy(ContractNotifier::class);
        $this->app->instance(ContractNotifier::class, $this->notifier);

        $this->compras = Sector::create(['name' => 'Compras']);
        $this->criaPresidente();

        $destinatarios = \Mockery::mock(ContractRecipients::class);
        $destinatarios->shouldReceive('reviewers')->andReturn(['coordenador.gerencia@example.com']);
        $destinatarios->shouldReceive('author')->andReturn('compradora@example.com');
        $this->app->instance(ContractRecipients::class, $destinatarios);

        $diretores = \Mockery::mock(DirectorAreaService::class);
        $diretores->shouldReceive('options')->andReturn([]);
        $diretores->shouldReceive('signerData')->andReturnUsing(fn(int $area, int $user) => [
            'name' => 'Alex Ribeiro da Silva',
            'cpf' => '52998224725',
            'email' => 'presidente@example.com',
            'phone' => null,
            'member_id' => null,
            'user_id' => $user,
            'director_area_id' => $area,
            'director_area_name' => 'Presidência',
            'director_role' => 'primary',
        ]);
        $this->app->instance(DirectorAreaService::class, $diretores);
    }

    private function service(): ContractService
    {
        return app(ContractService::class);
    }

    private function contrato(array $vigencia = ['months' => 12]): SignatureContract
    {
        $contrato = $this->service()->create($this->compras->id, $this->compras->name, null, null, 7, 'Compradora');
        $contrato->forceFill(['content' => ContractContent::normalize(array_merge($this->conteudoCompleto(), ['validity' => $vigencia]))])->save();

        return $contrato->fresh();
    }

    private function emAssinatura(array $vigencia = ['months' => 12]): SignatureContract
    {
        $contrato = $this->contrato($vigencia);
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        app(SignatureDocumentService::class)->freeze($contrato->fresh()->document);

        return $contrato->fresh();
    }

    private function assinaTudo(SignatureContract $contrato): SignatureContract
    {
        app(SignatureStateMachine::class)->documentTo($contrato->document, SignatureDocument::STATUS_SIGNED, SignatureAuditEvent::EVENT_SIGNED);

        return $contrato->fresh();
    }

    private function usuario(array $sectorIds, bool $gerencia = false): User
    {
        $user = $this->usuarioComPermissoes(['assinatura.contratos']);
        $user->shouldReceive('sectorIds')->andReturn($sectorIds);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);
        $user->id = 1;

        return $user;
    }

    // ------------------------------------------------------------- vigência

    public function test_vigencia_normaliza_prazo_e_data(): void
    {
        $this->assertSame(['months' => 12, 'until' => null], ContractContent::validity(['months' => '12', 'until' => '']));
        $this->assertSame(['months' => null, 'until' => '2027-12-31'], ContractContent::validity(['months' => '0', 'until' => '2027-12-31']));
        $this->assertSame(['months' => null, 'until' => null], ContractContent::validity(['until' => 'não é data']));
        $this->assertSame(ContractContent::MAX_VALIDITY_MONTHS, ContractContent::validity(['months' => 9999])['months']);
        $this->assertArrayHasKey('validity', ContractContent::normalize([]));
    }

    public function test_termino_conta_da_assinatura(): void
    {
        $contrato = $this->emAssinatura(['months' => 12]);
        $this->assertNull($contrato->expires_on);
        $this->assertSame('12 meses a partir da assinatura', $contrato->validityLabel());

        $contrato = $this->assinaTudo($contrato);

        $this->assertNotNull($contrato->signed_at);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateString(), $contrato->expires_on->toDateString());
        $this->assertStringContainsString('vigência até', $contrato->events()->where('event', 'signed')->first()->label());
        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'signed', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT], \Mockery::type('string'))
            ->once();
    }

    public function test_data_fixa_vale_no_lugar_do_prazo(): void
    {
        $contrato = $this->assinaTudo($this->emAssinatura(['months' => 12, 'until' => '2027-03-31']));

        $this->assertSame('2027-03-31', $contrato->expires_on->toDateString());
    }

    public function test_avisa_a_60_30_e_15_dias_uma_vez_cada(): void
    {
        $contrato = $this->assinaTudo($this->emAssinatura());
        $contrato->forceFill(['expires_on' => now()->addDays(25)->toDateString()])->save();

        // Passou de 60 e 30 de uma vez: um aviso só, e os dois marcos ficam marcados.
        $this->assertSame(1, $this->service()->sendExpiryAlerts());
        $this->assertEqualsCanonicalizing([60, 30], $contrato->fresh()->expiry_alerts);
        $this->assertSame(0, $this->service()->sendExpiryAlerts());

        $contrato->forceFill(['expires_on' => now()->addDays(10)->toDateString()])->save();
        $this->assertSame(1, $this->service()->sendExpiryAlerts());
        $this->assertEqualsCanonicalizing([60, 30, 15], $contrato->fresh()->expiry_alerts);

        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'expiring', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_SECTOR], \Mockery::pattern('/termina em/'))
            ->twice();
    }

    public function test_arquivado_cancelado_e_longe_do_fim_nao_avisam(): void
    {
        $longe = $this->assinaTudo($this->emAssinatura());
        $arquivado = $this->assinaTudo($this->emAssinatura());
        $arquivado->forceFill(['expires_on' => now()->addDays(5)->toDateString(), 'archived_at' => now()])->save();

        $this->assertSame(0, $this->service()->sendExpiryAlerts());
        $this->assertNull($longe->fresh()->expiry_alerts);
    }

    public function test_aba_vencendo_e_aviso_na_lista(): void
    {
        $perto = $this->assinaTudo($this->emAssinatura());
        $perto->forceFill(['expires_on' => now()->addDays(20)->toDateString()])->save();
        $longe = $this->assinaTudo($this->emAssinatura());
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.index', ['aba' => 'vencendo']))
            ->assertOk()
            ->assertSee(route('contracts.show', $perto))
            ->assertDontSee(route('contracts.show', $longe))
            ->assertSee('Vigente até ' . now()->addDays(20)->format('d/m/Y'));

        $this->actingAs($compradora)->get(route('contracts.show', $perto))
            ->assertOk()
            ->assertSee('Vence em 20 dias');
    }

    public function test_informa_vigencia_de_contrato_ja_assinado(): void
    {
        $contrato = $this->assinaTudo($this->emAssinatura([]));
        $this->assertNull($contrato->expires_on);

        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.validity', $contrato), ['validity' => ['months' => 6]])
            ->assertRedirect(route('contracts.show', $contrato));

        $contrato = $contrato->fresh();
        $this->assertSame(6, $contrato->content['validity']['months']);
        $this->assertSame($contrato->signed_at->copy()->startOfDay()->addMonthsNoOverflow(6)->toDateString(), $contrato->expires_on->toDateString());
        $this->assertSame(1, $contrato->events()->where('event', 'validity_changed')->count());
    }

    public function test_editor_tem_o_campo_de_vigencia_e_grava(): void
    {
        $contrato = $this->contrato([]);
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)->get(route('contracts.edit', $contrato))
            ->assertOk()
            ->assertSee('Vigência (meses a partir da assinatura)')
            ->assertSee('name="validity[months]"', false);

        $this->service()->save($contrato, array_merge($contrato->content, ['validity' => ['months' => 24]]), 7, 'Compradora');
        $this->assertSame(24, $contrato->fresh()->content['validity']['months']);
    }

    // --------------------------------------------------------- notificações

    public function test_movimentacoes_notificam_gerencia_e_quem_redigiu(): void
    {
        $contrato = $this->contrato();

        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'submitted', [ContractNotifier::TO_MANAGEMENT], \Mockery::pattern('/Compradora enviou/'), 7)
            ->once();

        // Ajuste da Gerência no texto durante a validação.
        $this->service()->save($contrato->fresh(), $contrato->content, 9, 'Gerente');
        $this->notifier->shouldHaveReceived('adjusted')->with(\Mockery::type(SignatureContract::class), 9, 'Gerente')->once();

        $this->service()->returnToAuthor($contrato->fresh(), 'Ajuste a cláusula 2.', [], 9, 'Gerente');
        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'returned', [ContractNotifier::TO_AUTHOR], \Mockery::type('string'), 9)
            ->once();

        // Quem redige salvando (fora da validação) não gera aviso de ajuste.
        $this->service()->save($contrato->fresh(), $contrato->content, 7, 'Compradora');
        $this->notifier->shouldHaveReceived('adjusted')->once();

        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'approved', [ContractNotifier::TO_AUTHOR], \Mockery::pattern('/aprovou/'), 9)
            ->once();

        $this->service()->cancel($contrato->fresh(), 'Desistência', 9, 'Gerente');
        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'canceled', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT], \Mockery::pattern('/Desistência/'), 9)
            ->once();
    }

    public function test_recusa_do_presidente_notifica(): void
    {
        $contrato = $this->emAssinatura();

        $this->service()->refuseByPresident($contrato, 'A cláusula 4 precisa de multa.', 50, 'Alex Ribeiro da Silva');

        $this->notifier->shouldHaveReceived('notify')
            ->with(\Mockery::type(SignatureContract::class), 'refused', [ContractNotifier::TO_AUTHOR, ContractNotifier::TO_MANAGEMENT], \Mockery::pattern('/recusou/'))
            ->once();
    }

    public function test_notificacao_vai_para_o_sino_e_o_navegador(): void
    {
        $contrato = $this->contrato();
        $dados = (new ContractMovement($contrato, 'submitted', 'Contrato para validar', 'Fulano enviou X.'))->toArray(new \stdClass());

        $this->assertSame('contract_submitted', $dados['type']);
        $this->assertSame('Contrato para validar', $dados['title']);
        $this->assertSame('Fulano enviou X.', $dados['message']);
        $this->assertSame(route('contracts.show', $contrato, false), $dados['url']);
        $this->assertSame(['database'], (new ContractMovement($contrato, 'submitted', 't', 'm'))->via(new \stdClass()));
    }

    public function test_notificador_nao_avisa_quem_fez_e_nao_derruba_o_fluxo(): void
    {
        $contrato = $this->contrato();
        $quemFez = new User();
        $quemFez->id = 7;
        $outro = new User();
        $outro->id = 8;

        $notificador = new class([$quemFez, $outro]) extends ContractNotifier {
            public array $enviados = [];

            public function __construct(private array $usuarios)
            {
            }

            protected function recipients(SignatureContract $contract, array $to): \Illuminate\Support\Collection
            {
                return collect($this->usuarios);
            }
        };

        \Illuminate\Support\Facades\Notification::fake();
        $notificador->notify($contrato, 'submitted', [ContractNotifier::TO_MANAGEMENT], 'msg', 7);

        \Illuminate\Support\Facades\Notification::assertSentTo($outro, ContractMovement::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($quemFez, ContractMovement::class);

        // Falha ao achar os destinatários: registra e segue, sem exceção para o fluxo.
        $quebrado = new class extends ContractNotifier {
            protected function recipients(SignatureContract $contract, array $to): \Illuminate\Support\Collection
            {
                throw new \RuntimeException('sem banco');
            }
        };
        $quebrado->notify($contrato, 'submitted', [ContractNotifier::TO_AUTHOR], 'msg');
        $this->assertTrue(true);
    }
}
