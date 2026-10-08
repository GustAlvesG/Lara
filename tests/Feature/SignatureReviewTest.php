<?php

namespace Tests\Feature;

use App\Authorization\Permissions as P;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureReview;
use App\Models\SignatureTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * A revisão interna do documento assinado: outra pessoa, do dia seguinte em
 * diante, confere os itens do modelo. A coordenação revisa antes e os próprios.
 */
class SignatureReviewTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Carbon::setTestNow('2026-10-08 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Concluído hoje às 10h, gerado pelo usuário 1. */
    private function concluido(): SignatureDocument
    {
        $modelo = $this->criaModeloDeAssinatura([
            'review_items' => [
                ['key' => 'rev_cadastro', 'label' => 'Cadastro atualizado'],
                ['key' => 'rev_pagamento', 'label' => 'Pagamento lançado'],
            ],
        ]);

        $documento = $this->criaDocumentoDeAssinatura(['template' => $modelo, 'created_by' => 1, 'created_by_name' => 'Ana']);
        $documento->forceFill([
            'status' => SignatureDocument::STATUS_FINALIZED,
            'frozen_at' => '2026-10-08 09:00:00',
            'finalized_at' => '2026-10-08 10:00:00',
        ])->save();

        return $documento->fresh();
    }

    /** @param  array<int, string>  $permissoes */
    private function usuario(int $id, array $permissoes = [P::ASSINATURA_REVISAR]): User
    {
        $usuario = $this->usuarioComPermissoes($permissoes);
        $usuario->id = $id;
        $usuario->name = 'Usuário ' . $id;

        return $usuario;
    }

    private function revisa(User $usuario, SignatureDocument $documento, array $dados)
    {
        return $this->actingAs($usuario)->post(route('signature-documents.review', $documento), $dados);
    }

    public function test_so_fica_disponivel_no_dia_seguinte(): void
    {
        $documento = $this->concluido();

        $this->revisa($this->usuario(2), $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('warning', 'Disponível para revisão a partir de 09/10/2026.');

        $this->assertSame(0, SignatureReview::count());

        Carbon::setTestNow('2026-10-09 00:00:01');

        $this->revisa($this->usuario(2), $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('success');

        $documento->refresh();
        $this->assertSame(SignatureReview::RESULT_OK, $documento->review_status);
        $this->assertFalse($documento->awaitsReview());
    }

    public function test_quem_acompanhou_nao_revisa(): void
    {
        $documento = $this->concluido();
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Quem gerou o documento.
        $this->revisa($this->usuario(1), $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('warning', 'Você acompanhou a assinatura deste documento: a revisão é de outra pessoa.');

        // Quem gerou o QR também acompanhou.
        SignatureRequest::create([
            'signature_signer_id' => $documento->signers()->first()->id,
            'token_hash' => str_repeat('a', 64),
            'status' => SignatureRequest::STATUS_CONSUMED,
            'expires_at' => now(),
            'created_by' => 3,
        ]);

        $this->revisa($this->usuario(3), $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('warning');

        $this->assertSame(0, SignatureReview::count());
    }

    public function test_coordenacao_revisa_antes_e_o_proprio_e_fica_dito(): void
    {
        $documento = $this->concluido();

        $this->revisa($this->usuario(1, [P::ASSINATURA_REVISAR_COORDENACAO]), $documento, [
            'resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento'],
        ])->assertSessionHas('success');

        $revisao = SignatureReview::firstOrFail();

        $this->assertTrue($revisao->early);
        $this->assertTrue($revisao->own);
        $this->assertSame('Usuário 1', $revisao->reviewed_by_name);
    }

    public function test_tudo_em_ordem_exige_todos_os_itens_e_pendencia_exige_observacao(): void
    {
        $documento = $this->concluido();
        Carbon::setTestNow('2026-10-09 08:00:00');
        $revisor = $this->usuario(2);

        $this->revisa($revisor, $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro']])
            ->assertSessionHas('warning', fn(string $m) => str_contains($m, 'Pagamento lançado'));

        $this->revisa($revisor, $documento, ['resultado' => 'issues', 'feitos' => ['rev_cadastro']])
            ->assertSessionHas('warning', 'Com pendência, diga na observação o que falta fazer.');

        $this->assertSame(0, SignatureReview::count());
    }

    public function test_pendencia_mantem_na_fila_ate_revisao_em_ordem(): void
    {
        $documento = $this->concluido();
        Carbon::setTestNow('2026-10-09 08:00:00');
        $revisor = $this->usuario(2);

        $this->revisa($revisor, $documento, [
            'resultado' => 'issues', 'feitos' => ['rev_cadastro'], 'observacao' => 'Falta lançar o pagamento.',
        ])->assertSessionHas('success');

        $documento->refresh();
        $this->assertSame(SignatureReview::RESULT_ISSUES, $documento->review_status);
        $this->assertTrue($documento->awaitsReview());

        $primeira = SignatureReview::firstOrFail();
        $this->assertSame([true, false], array_column($primeira->items, 'done'));

        // A trilha registra a revisão — com o que ficou pendente, sem a observação.
        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_REVIEWED)->firstOrFail();
        $this->assertSame(['Pagamento lançado'], $evento->payload['pendentes']);
        $this->assertArrayNotHasKey('observacao', $evento->payload);

        $this->revisa($revisor, $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('success');

        // Em ordem, acabou: não há nova revisão.
        $this->revisa($revisor, $documento, ['resultado' => 'ok', 'feitos' => ['rev_cadastro', 'rev_pagamento']])
            ->assertSessionHas('warning', 'Este documento já foi revisado e está em ordem.');

        $this->assertSame(2, SignatureReview::count());
    }

    public function test_documento_nao_concluido_nao_e_revisado(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->revisa($this->usuario(2, [P::ASSINATURA_REVISAR_COORDENACAO]), $documento, ['resultado' => 'ok'])
            ->assertSessionHas('warning', 'Só documento concluído passa pela revisão.');
    }

    public function test_sem_permissao_nao_revisa_nem_ve_a_fila(): void
    {
        $documento = $this->concluido();
        $atendente = $this->usuario(2, [P::ASSINATURA_DOCUMENTOS]);

        $this->revisa($atendente, $documento, ['resultado' => 'ok'])->assertForbidden();
        $this->actingAs($atendente)->get(route('signature-reviews.index'))->assertForbidden();
    }

    public function test_fila_mostra_o_motivo_e_a_aba_o_formulario(): void
    {
        $documento = $this->concluido();

        // No dia: aparece na fila, com a data em que fica disponível.
        $this->actingAs($this->usuario(2))->get(route('signature-reviews.index'))
            ->assertOk()
            ->assertSee($documento->title)
            ->assertSee('Disponível para revisão a partir de 09/10/2026.');

        Carbon::setTestNow('2026-10-09 08:00:00');

        $this->actingAs($this->usuario(2))->get(route('signature-documents.show', [$documento, 'aba' => 'revisao']))
            ->assertOk()
            ->assertSee('data-review-form', false)
            ->assertSee('Cadastro atualizado')
            ->assertSee('Registrar revisão');

        // Quem acompanhou vê o motivo, não o formulário.
        $this->actingAs($this->usuario(1))->get(route('signature-documents.show', [$documento, 'aba' => 'revisao']))
            ->assertOk()
            ->assertDontSee('data-review-form', false)
            ->assertSee('Você acompanhou a assinatura deste documento');
    }

    public function test_modelo_grava_os_itens_da_revisao(): void
    {
        $this->actingAs($this->usuarioComPermissoes([P::ASSINATURA_MODELOS]))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo',
                'body_html' => '<p>Texto.</p>[[assinatura]]',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'review_items' => [['label' => 'Cadastro atualizado'], ['label' => ''], ['label' => 'Cadastro atualizado']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['key' => 'rev_cadastro_atualizado', 'label' => 'Cadastro atualizado'],
            ['key' => 'rev_cadastro_atualizado_2', 'label' => 'Cadastro atualizado'],
        ], SignatureTemplate::firstOrFail()->review_items);
    }
}
