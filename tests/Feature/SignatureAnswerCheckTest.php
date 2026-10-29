<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAnswerCheck;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureAnswerCheckService;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureFieldTypes as T;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Conferência das respostas do tablet pelo atendente (29/10/2026).
 *
 * No balcão, documento com pergunta a quem assina só é assinado depois de o
 * atendente conferir as respostas: ele as vê enquanto a pessoa digita, e
 * aprova, devolve com observação ou corrige. A aprovação vale para o
 * original em que foi dada. Remoto, Diretoria e Termo de Menores ficam de
 * fora. Ver SignatureAnswerCheckService.
 */
class SignatureAnswerCheckTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function congelado(array $perguntados = ['telefone']): SignatureDocument
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Espaço [[espaco]]. Contato: [[telefone]].</p>[[assinatura]]',
            'identity_check' => SignatureTemplate::IDENTITY_NONE,
            'requires_photo' => false,
            'variables' => [
                ['key' => 'espaco', 'label' => 'Espaço', 'type' => T::TEXT],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => T::PHONE, 'required' => true, 'question' => 'Qual é o seu telefone?'],
            ],
        ]);

        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura([
            'template' => $modelo,
            // Sem a pergunta, o telefone (obrigatório) é do atendente.
            'data' => in_array('telefone', $perguntados, true)
                ? ['espaco' => 'Piscina']
                : ['espaco' => 'Piscina', 'telefone' => '24999991234'],
            'signer_field_keys' => $perguntados,
        ]));
    }

    /** @return array{document: SignatureDocument, cookie: string, payload: array<string, mixed>} */
    private function sessaoAberta(SignatureDocument $documento): array
    {
        $liberacao = app(SignatureRequestService::class)->issue($documento->nextSigner());

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk();

        return [
            'document' => $documento->fresh(),
            'cookie' => $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue(),
            'payload' => $resposta->json(),
        ];
    }

    private function tablet(string $cookie): self
    {
        return $this->withCredentials()->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
    }

    private function atendente(): self
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']));
    }

    /** @return array<string, mixed> */
    private function assinatura(): array
    {
        $pontos = [];

        for ($i = 0; $i < 60; $i++) {
            $pontos[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        return [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'strokes' => [['points' => $pontos]],
            'accepted' => true,
        ];
    }

    public function test_atendente_ve_o_rascunho_enquanto_a_pessoa_digita(): void
    {
        ['document' => $documento, 'cookie' => $cookie, 'payload' => $payload] = $this->sessaoAberta($this->congelado());

        $this->assertSame(['required' => true, 'status' => 'filling', 'note' => null], $payload['answer_check']);

        $this->tablet($cookie)
            ->postJson(route('quiosque.answers-draft', $documento), ['answers' => ['telefone' => '(24) 9999', 'outra' => 'x']])
            ->assertOk();

        $estado = $this->atendente()->getJson(route('signature-documents.status', $documento))
            ->assertOk()
            ->json('answer_check');

        $this->assertSame('filling', $estado['status']);
        $this->assertSame('(24) 9999', $estado['fields'][0]['answer']);
        $this->assertSame('Qual é o seu telefone?', $estado['fields'][0]['question']);

        // O rascunho não toca o documento nem a trilha.
        $this->assertNull($documento->fresh()->signing_data['telefone'] ?? null);
        $this->assertSame(0, $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_FORM_ANSWERED)->count());
    }

    public function test_assinatura_espera_a_aprovacao_do_atendente(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta($this->congelado());

        $this->tablet($cookie)
            ->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '24999991234']])
            ->assertOk()
            ->assertJsonPath('answer_check.status', 'pending');

        $this->tablet($cookie)
            ->postJson(route('quiosque.sign', $documento), $this->assinatura())
            ->assertStatus(409)
            ->assertJsonPath('error', 'O atendente ainda não conferiu as suas respostas. Aguarde a conferência para assinar.');

        $this->atendente()->postJson(route('signature-documents.answers.approve', $documento))
            ->assertOk()
            ->assertJsonPath('answer_check.status', 'approved')
            ->assertJsonPath('answer_check.checked_by', 'Atendente de Teste');

        $this->tablet($cookie)->getJson(route('quiosque.session'))->assertJsonPath('answer_check.status', 'approved');

        $this->tablet($cookie)->postJson(route('quiosque.sign', $documento), $this->assinatura())->assertOk();

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_ANSWERS_APPROVED)->sole();
        $this->assertSame(SignatureAuditEvent::ACTOR_USER, $evento->actor_type);
    }

    public function test_devolver_leva_o_recado_ao_tablet_e_o_reenvio_volta_a_conferencia(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta($this->congelado());

        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '24999991234']]);

        // Sem recado, não há o que a pessoa corrigir.
        $this->atendente()->postJson(route('signature-documents.answers.return', $documento), ['note' => ''])
            ->assertStatus(422);

        Carbon::setTestNow('2026-10-29 10:01:00');
        $this->atendente()->postJson(route('signature-documents.answers.return', $documento), ['note' => 'Falta o DDD certo.'])
            ->assertOk()
            ->assertJsonPath('answer_check.status', 'returned');

        $this->tablet($cookie)->getJson(route('quiosque.session'))
            ->assertJsonPath('answer_check.status', 'returned')
            ->assertJsonPath('answer_check.note', 'Falta o DDD certo.');

        // A observação não vai à trilha (o manifesto é impresso).
        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_ANSWERS_RETURNED)->sole();
        $this->assertStringNotContainsString('DDD', json_encode($evento->payload));

        Carbon::setTestNow('2026-10-29 10:02:00');
        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '21999991234']])
            ->assertJsonPath('answer_check.status', 'pending');
    }

    public function test_correcao_do_atendente_refaz_o_documento_e_aprova(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta($this->congelado());

        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '24999991234']]);
        $hashAntes = $documento->fresh()->original_sha256;

        // Resposta inválida volta com o erro do campo e não grava.
        $this->atendente()->postJson(route('signature-documents.answers.correct', $documento), ['answers' => ['telefone' => '12']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['telefone']]);

        $this->atendente()->postJson(route('signature-documents.answers.correct', $documento), ['answers' => ['telefone' => '(21) 98888-7777']])
            ->assertOk()
            ->assertJsonPath('answer_check.status', 'approved');

        $documento->refresh();

        $this->assertSame('21988887777', $documento->signing_data['telefone']);
        $this->assertNotSame($hashAntes, $documento->original_sha256);

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_FORM_CORRECTED)->sole();
        $this->assertSame(['telefone'], $evento->payload['campos']);
        $this->assertSame(SignatureAuditEvent::ACTOR_USER, $evento->actor_type);

        $this->assertSame(
            [SignatureAnswerCheck::ACTION_CORRECTED, SignatureAnswerCheck::ACTION_APPROVED],
            $documento->answerChecks()->pluck('action')->all(),
        );

        // O tablet recebe o PDF novo (e a tela manda ler de novo).
        $this->tablet($cookie)->getJson(route('quiosque.session'))
            ->assertJsonPath('document.pdf_version', substr($documento->original_sha256, 0, 12));
    }

    public function test_aprovacao_nao_vale_para_resposta_mudada_depois(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta($this->congelado());

        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '24999991234']]);
        $this->atendente()->postJson(route('signature-documents.answers.approve', $documento))->assertOk();

        // A pessoa voltou e mudou a resposta: é outro documento.
        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '21999991234']])
            ->assertJsonPath('answer_check.status', 'pending');

        $this->tablet($cookie)->postJson(route('quiosque.sign', $documento), $this->assinatura())->assertStatus(409);
    }

    public function test_aprovar_antes_da_resposta_e_recusado(): void
    {
        ['document' => $documento] = $this->sessaoAberta($this->congelado());

        $this->atendente()->postJson(route('signature-documents.answers.approve', $documento))
            ->assertStatus(409)
            ->assertJsonPath('error', 'A pessoa ainda está respondendo no tablet. Aguarde ela tocar em "Continuar".');
    }

    public function test_sem_pergunta_nao_ha_conferencia(): void
    {
        ['document' => $documento, 'payload' => $payload, 'cookie' => $cookie] = $this->sessaoAberta($this->congelado([]));

        $this->assertNull($payload['answer_check']);
        $this->assertNull($this->atendente()->getJson(route('signature-documents.status', $documento))->json('answer_check'));

        $this->tablet($cookie)->postJson(route('quiosque.sign', $documento), $this->assinatura())->assertOk();
    }

    public function test_remoto_e_diretoria_ficam_de_fora(): void
    {
        $servico = app(SignatureAnswerCheckService::class);
        $documento = $this->congelado();
        $signatario = $documento->signers()->first();

        $remota = new SignatureRequest(['identity_method' => SignatureRequest::IDENTITY_METHOD_REMOTE_LINK]);
        $remota->setRelation('signer', $signatario);
        $diretor = new SignatureRequest(['identity_method' => SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN]);
        $diretor->setRelation('signer', $signatario);
        $balcao = new SignatureRequest();
        $balcao->setRelation('signer', $signatario);

        $this->assertFalse($servico->requiredFor($remota));
        $this->assertFalse($servico->requiredFor($diretor));
        $this->assertTrue($servico->requiredFor($balcao));
    }

    public function test_depois_da_primeira_assinatura_nao_ha_mais_conferencia(): void
    {
        $documento = $this->congelado();
        SignatureSigner::create([
            'signature_document_id' => $documento->id, 'name' => 'João', 'cpf' => '52998224725', 'position' => 2,
        ]);

        ['cookie' => $cookie] = $this->sessaoAberta($documento);
        $this->tablet($cookie)->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '24999991234']]);
        $this->atendente()->postJson(route('signature-documents.answers.approve', $documento))->assertOk();
        $this->tablet($cookie)->postJson(route('quiosque.sign', $documento), $this->assinatura())->assertOk();

        // O segundo signatário assina o texto já fechado: nada a conferir.
        ['payload' => $payload] = $this->sessaoAberta($documento->fresh());

        $this->assertNull($payload['answer_check']);
    }

    public function test_tela_do_documento_traz_o_cartao_de_conferencia(): void
    {
        $documento = $this->congelado();

        $this->atendente()->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Conferência das respostas')
            ->assertSee(route('signature-documents.answers.approve', $documento), false);
    }
}
