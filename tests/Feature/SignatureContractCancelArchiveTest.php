<?php

namespace Tests\Feature;

use App\Exceptions\ContractException;
use App\Mail\SignatureContractCanceledMail;
use App\Models\Sector;
use App\Models\SignatureContract;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Contratos — cancelar e arquivar (05/11/2026, decisões do usuário):
 *
 *  - cancelar em qualquer fase até a assinatura terminar; em assinatura, o
 *    documento é cancelado junto; assinado não cancela;
 *  - arquivar qualquer contrato: só sai das abas e vai para "Arquivados";
 *    desarquiva;
 *  - o setor e a Gerência; motivo obrigatório nos dois.
 */
class SignatureContractCancelArchiveTest extends TestCase
{
    use CreatesContractSchema;
    use MocksSignatureUser;

    private Sector $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

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

    private function contrato(): SignatureContract
    {
        $contrato = $this->service()->create($this->compras->id, $this->compras->name, null, null, 7, 'Compradora');
        $contrato->forceFill(['content' => $this->conteudoCompleto()])->save();

        return $contrato->fresh();
    }

    private function emAssinatura(): SignatureContract
    {
        $contrato = $this->contrato();
        $this->service()->submit($contrato->fresh(), null, 7, 'Compradora');
        $this->service()->approve($contrato->fresh(), null, 9, 'Gerente');
        app(SignatureDocumentService::class)->freeze($contrato->fresh()->document);

        return $contrato->fresh();
    }

    private function usuario(array $sectorIds, bool $gerencia = false, array $permissoes = ['assinatura.contratos']): User
    {
        $user = $this->usuarioComPermissoes($permissoes);
        $user->shouldReceive('sectorIds')->andReturn($sectorIds);
        $user->shouldReceive('isManagementMember')->andReturn($gerencia);
        $user->id = 1;

        return $user;
    }

    public function test_cancela_rascunho_com_motivo_e_avisa_quem_redigiu(): void
    {
        $contrato = $this->contrato();

        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.cancel', $contrato), ['reason' => 'O fornecedor desistiu.'])
            ->assertRedirect(route('contracts.show', $contrato));

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_CANCELED, $contrato->status);
        $this->assertStringContainsString('Motivo: O fornecedor desistiu.', $contrato->events()->where('event', 'canceled')->first()->label());
        Mail::assertSent(SignatureContractCanceledMail::class, fn($m) => $m->hasTo('compradora@example.com'));
    }

    public function test_cancelar_sem_motivo_nao_cancela(): void
    {
        $contrato = $this->contrato();

        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.cancel', $contrato), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(SignatureContract::STATUS_DRAFT, $contrato->fresh()->status);
    }

    public function test_cancelar_em_assinatura_cancela_o_documento_junto(): void
    {
        $contrato = $this->emAssinatura();
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $contrato->document->status);

        $this->service()->cancel($contrato, 'Serviço não será mais contratado.', 9, 'Gerente');

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_CANCELED, $contrato->status);
        $this->assertSame(SignatureDocument::STATUS_CANCELED, $contrato->document->status);
        $this->assertSame(0, $contrato->document->signers()->where('status', SignatureSigner::STATUS_PENDING)->count());
        $this->assertStringContainsString('cancelado junto', $contrato->events()->where('event', 'canceled')->first()->label());
    }

    public function test_assinado_nao_cancela(): void
    {
        $contrato = $this->emAssinatura();
        $contrato->document->forceFill(['status' => SignatureDocument::STATUS_FINALIZED])->save();
        $contrato = $contrato->fresh();

        $this->assertFalse($contrato->canBeCanceled());
        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.cancel', $contrato), ['reason' => 'Teste'])
            ->assertForbidden();

        $this->expectException(ContractException::class);
        $this->service()->cancel($contrato, 'Teste', 9, 'Gerente');
    }

    public function test_arquivar_tira_das_abas_e_desarquivar_devolve(): void
    {
        $contrato = $this->contrato();
        $outro = $this->contrato();
        $compradora = $this->usuario([$this->compras->id]);

        $this->actingAs($compradora)
            ->post(route('contracts.archive', $contrato), ['reason' => 'Rascunho que não vai seguir.'])
            ->assertRedirect(route('contracts.show', $contrato));

        $contrato = $contrato->fresh();
        $this->assertTrue($contrato->isArchived());
        $this->assertSame(SignatureContract::STATUS_DRAFT, $contrato->status);
        $this->assertStringContainsString('Motivo: Rascunho que não vai seguir.', $contrato->events()->where('event', 'archived')->first()->label());

        $this->actingAs($compradora)->get(route('contracts.index'))
            ->assertOk()
            ->assertDontSee(route('contracts.show', $contrato))
            ->assertSee(route('contracts.show', $outro));

        $this->actingAs($compradora)->get(route('contracts.index', ['aba' => 'arquivados']))
            ->assertOk()
            ->assertSee(route('contracts.show', $contrato))
            ->assertDontSee(route('contracts.show', $outro));

        $this->actingAs($compradora)->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Desarquivar');

        $this->actingAs($compradora)->post(route('contracts.unarchive', $contrato))->assertRedirect();
        $this->assertFalse($contrato->fresh()->isArchived());
    }

    public function test_arquivar_pede_motivo_e_e_do_setor(): void
    {
        $contrato = $this->contrato();

        $this->actingAs($this->usuario([$this->compras->id]))
            ->post(route('contracts.archive', $contrato), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->usuario([999]))
            ->post(route('contracts.archive', $contrato), ['reason' => 'Outro setor'])
            ->assertForbidden();

        $this->assertFalse($contrato->fresh()->isArchived());
    }

    public function test_tela_do_contrato_mostra_cancelar_e_arquivar(): void
    {
        $contrato = $this->emAssinatura();

        $this->actingAs($this->usuario([$this->compras->id]))->get(route('contracts.show', $contrato))
            ->assertOk()
            ->assertSee('Cancelar ou arquivar')
            ->assertSee('O documento de assinatura é cancelado junto');
    }
}
