<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * O caminho degradado para ambiente SEM HTTPS.
 *
 * `getUserMedia` só existe em origem segura, então em HTTP comum o tablet não
 * lê QR e não tira foto. Duas flags tornam esse cenário operável:
 *
 *  - `manual_code.enabled` — o atendente dita um código de 8 caracteres;
 *  - `evidence.skip_photo_without_camera` — a assinatura conclui sem a foto,
 *    com a ausência REGISTRADA e impressa no manifesto.
 *
 * As duas nascem DESLIGADAS, e o que este teste mais protege é isso: com elas
 * desligadas, nada muda em relação ao fluxo normal.
 */
class SignatureWithoutHttpsTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();

        $this->requests = app(SignatureRequestService::class);
    }

    private function ligaCodigoManual(): void
    {
        config(['signature.manual_code.enabled' => true]);
    }

    private function documentoLiberado(array $modelo = []): SignatureDocument
    {
        $template = $this->criaModeloDeAssinatura($modelo);

        return app(SignatureDocumentService::class)
            ->freeze($this->criaDocumentoDeAssinatura(['template' => $template]));
    }

    /* ------------------------------------------------------------------
     | Código digitado
     |------------------------------------------------------------------*/

    /** Desligado é desligado: nem código é gerado, nem a rota o aceita. */
    public function test_sem_a_flag_nao_existe_codigo_digitado(): void
    {
        $documento = $this->documentoLiberado();

        $liberacao = $this->requests->issue($documento->signers()->first());

        $this->assertNull($liberacao['manual_code']);
        $this->assertNull($liberacao['request']->fresh()->manual_code_hash);

        $this->postJson(route('quiosque.consume'), ['code' => 'A7K29MPX'])
            ->assertStatus(404);
    }

    public function test_codigo_e_gerado_com_a_flag_ligada_e_so_o_hash_vai_ao_banco(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $codigo = $liberacao['manual_code'];

        $this->assertSame(8, strlen($codigo));
        // Alfabeto sem 0/O e 1/I/L — o código é ditado em voz alta.
        $this->assertSame(1, preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/', $codigo));

        $linha = DB::table('signature_requests')->where('id', $liberacao['request']->id)->first();

        $this->assertSame(hash('sha256', $codigo), $linha->manual_code_hash);
        $this->assertStringNotContainsString($codigo, json_encode($linha));
    }

    public function test_codigo_digitado_abre_a_sessao(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $resposta = $this->postJson(route('quiosque.consume'), [
            'code' => $liberacao['manual_code'],
        ]);

        $resposta->assertOk()->assertJsonPath('document.id', $documento->id);
        $this->assertNotNull($resposta->getCookie(EnsureSignatureKioskSession::COOKIE));

        $this->assertSame(
            SignatureRequest::STATUS_CONSUMED,
            $liberacao['request']->fresh()->status,
        );
    }

    /**
     * O código é exibido em dois blocos ("A7K2 9MPX") e alguém vai digitar o
     * espaço. Recusar por isso seria transformar a apresentação em regra.
     */
    public function test_codigo_aceita_espacos_e_minusculas(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $codigo = $liberacao['manual_code'];
        $digitado = mb_strtolower(substr($codigo, 0, 4) . ' ' . substr($codigo, 4));

        $this->postJson(route('quiosque.consume'), ['code' => $digitado])->assertOk();
    }

    /** Mesma trava do QR: a segunda leitura não abre nada. */
    public function test_codigo_vale_uma_vez_so(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $this->postJson(route('quiosque.consume'), ['code' => $liberacao['manual_code']])->assertOk();

        $this->postJson(route('quiosque.consume'), ['code' => $liberacao['manual_code']])
            ->assertStatus(409);

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_QR_REUSE_BLOCKED,
        ]);
    }

    /**
     * O código vence ANTES do QR: ele é ditado em voz alta no balcão, e quem
     * está na fila ouve.
     */
    public function test_codigo_expira_antes_do_qr(): void
    {
        $this->ligaCodigoManual();

        config([
            'signature.manual_code.ttl_seconds' => 150,
            'signature.qr_ttl_seconds' => 300,
        ]);

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $this->travel(160)->seconds();

        // Código morto…
        $this->postJson(route('quiosque.consume'), ['code' => $liberacao['manual_code']])
            ->assertStatus(410);

        // …e o QR da MESMA liberação ainda vivo.
        $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->assertOk();
    }

    public function test_codigo_regerado_invalida_o_anterior(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $signatario = $documento->signers()->first();

        $antigo = $this->requests->issue($signatario);
        $novo = $this->requests->issue($signatario);

        $this->postJson(route('quiosque.consume'), ['code' => $antigo['manual_code']])
            ->assertStatus(410);

        $this->postJson(route('quiosque.consume'), ['code' => $novo['manual_code']])
            ->assertOk();
    }

    public function test_codigo_inexistente_responde_de_forma_generica(): void
    {
        $this->ligaCodigoManual();

        $this->postJson(route('quiosque.consume'), ['code' => 'ZZZZZZZZ'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'QR Code inválido. Peça ao atendente que gere outro.');
    }

    public function test_consumo_exige_qr_ou_codigo(): void
    {
        $this->postJson(route('quiosque.consume'), [])
            ->assertStatus(422);
    }

    /** A trilha guarda COMO o tablet entrou — é o que explica um atendimento sem foto. */
    public function test_trilha_registra_que_a_entrada_foi_por_codigo(): void
    {
        $this->ligaCodigoManual();

        $documento = $this->documentoLiberado();
        $liberacao = $this->requests->issue($documento->signers()->first());

        $this->postJson(route('quiosque.consume'), ['code' => $liberacao['manual_code']])->assertOk();

        $evento = SignatureAuditEvent::where('signature_document_id', $documento->id)
            ->where('event', SignatureAuditEvent::EVENT_QR_CONSUMED)
            ->latest('id')
            ->first();

        $this->assertSame('codigo_digitado', $evento->payload['via']);
    }

    /* ------------------------------------------------------------------
     | Assinatura sem foto
     |------------------------------------------------------------------*/

    /** @return array{document: SignatureDocument, cookie: string} */
    private function sessaoPronta(): array
    {
        $documento = $this->documentoLiberado(['requires_photo' => true]);
        $liberacao = $this->requests->issue($documento->signers()->first());

        $cookie = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ])->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

        $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => '1234'])
            ->assertOk();

        return ['document' => $documento, 'cookie' => $cookie];
    }

    private function assina(string $cookie, SignatureDocument $documento, array $extra = [])
    {
        return $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie)
            ->postJson(route('quiosque.sign', $documento), array_merge([
                'signature' => 'data:image/png;base64,' . base64_encode(base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
                )),
                'strokes' => [['points' => array_fill(0, 40, ['x' => 1, 'y' => 2, 't' => 3])]],
                'accepted' => true,
            ], $extra));
    }

    /** Sem a flag, foto exigida continua sendo exigida. */
    public function test_sem_a_flag_a_foto_exigida_continua_obrigatoria(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoPronta();

        $this->assina($cookie, $documento, [
            'photo_skipped_reason' => SignatureEvidence::PHOTO_SKIP_NO_CAMERA,
        ])->assertStatus(422);

        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->first()->status);
    }

    public function test_com_a_flag_a_assinatura_conclui_sem_foto_e_registra_o_motivo(): void
    {
        config(['signature.evidence.skip_photo_without_camera' => true]);

        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoPronta();

        $this->assina($cookie, $documento, [
            'photo_skipped_reason' => SignatureEvidence::PHOTO_SKIP_NO_CAMERA,
        ])->assertOk();

        $evidencia = $documento->signers()->first()->evidence;

        $this->assertNull($evidencia->photo_path);
        $this->assertSame(SignatureEvidence::PHOTO_SKIP_NO_CAMERA, $evidencia->photo_skipped_reason);
        $this->assertStringContainsString('Câmera indisponível', $evidencia->photoSkipLabel());
    }

    /**
     * O motivo vem do cliente. Sem uma lista fechada, bastaria mandar qualquer
     * string para transformar a exigência de foto em sugestão.
     */
    public function test_motivo_inventado_e_recusado_mesmo_com_a_flag_ligada(): void
    {
        config(['signature.evidence.skip_photo_without_camera' => true]);

        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoPronta();

        $this->assina($cookie, $documento, ['photo_skipped_reason' => 'nao_quis'])
            ->assertStatus(422);

        $this->assina($cookie, $documento)->assertStatus(422);
    }

    /** A ausência da foto precisa aparecer no manifesto, não sumir. */
    public function test_manifesto_diz_que_a_foto_nao_foi_capturada(): void
    {
        config(['signature.evidence.skip_photo_without_camera' => true]);

        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoPronta();

        $this->assina($cookie, $documento, [
            'photo_skipped_reason' => SignatureEvidence::PHOTO_SKIP_NO_CAMERA,
        ])->assertOk();

        $html = app(\App\Services\Signature\SignatureDocumentRenderer::class)->html(
            $documento->fresh(),
            \App\Services\Signature\SignatureDocumentRenderer::MODE_FINAL,
        );

        $this->assertStringContainsString('Foto não capturada', $html);
        $this->assertStringContainsString('sem HTTPS', $html);
    }

    /** A tela do tablet oferece o caminho do código quando ele está ligado. */
    public function test_tela_do_tablet_oferece_a_entrada_por_codigo(): void
    {
        $this->ligaCodigoManual();

        $this->get(route('quiosque.index'))
            ->assertOk()
            ->assertSee('Digitar código')
            ->assertSee('codigoManual: true', false);
    }

    public function test_tela_do_tablet_nao_oferece_codigo_com_a_flag_desligada(): void
    {
        $this->get(route('quiosque.index'))
            ->assertOk()
            ->assertSee('codigoManual: false', false);
    }
}
