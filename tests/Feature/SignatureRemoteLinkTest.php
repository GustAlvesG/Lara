<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Jobs\FinalizeSignatureDocument;
use App\Mail\SignatureRemoteLinkMail;
use App\Models\SignatureAttachment;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureRemoteLink;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\Govbr\GovbrCheckService;
use App\Services\Signature\RemoteSignatureService;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Assinatura remota: o link por e-mail que abre a tela de quem assina no site
 * externo, e a API que o SERVIDOR do site chama.
 *
 * O que estes testes travam: o token só existe no e-mail e na tela, uma vez, de quem enviou; a API exige o token
 * próprio do site; a identidade é sempre o CPF completo e a foto é sempre
 * obrigatória; as 5 tentativas contam no LINK; o IP gravado é o de quem
 * assina (header do site); e a sessão remota não abre o tablet.
 */
class SignatureRemoteLinkTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private const API_TOKEN = 'token-do-site-de-teste-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();

        config([
            'signature.remote.site_url' => 'https://reservas.example.com',
            'signature.remote.api_token' => self::API_TOKEN,
            'signature.remote.allowed_ips' => [],
            'signature.remote.link_ttl_hours' => 72,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param array<string, mixed> $modelo */
    private function documentoCongelado(array $modelo = ['requires_photo' => false]): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura(
            ['template' => $this->criaModeloDeAssinatura($modelo)],
            ['email' => 'maria@example.com'],
        ));
    }

    /** Envia o link pelo painel e devolve o token, tirado do e-mail — como a pessoa. */
    private function enviaLink(SignatureDocument $documento, string $email = 'maria@example.com'): string
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.remote-link.store', [$documento, $documento->signers()->first()]), ['email' => $email])
            ->assertRedirect()
            ->assertSessionHas('success');

        $mail = Mail::sent(SignatureRemoteLinkMail::class)->last();
        preg_match('#/assinar/([A-Za-z0-9]{64})#', $mail->render(), $m);

        return $m[1];
    }

    /** O servidor do site chamando a API, com o IP de quem assina. */
    private function site(?string $sessao = null): self
    {
        $headers = [
            'Authorization' => 'Bearer ' . self::API_TOKEN,
            'X-Signer-Ip' => '177.10.20.30',
            'X-Signer-User-Agent' => 'Celular da Maria',
        ];

        if ($sessao !== null) {
            $headers['X-Signature-Session'] = $sessao;
        }

        return $this->withHeaders($headers);
    }

    private function abre(string $token): string
    {
        return $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertOk()
            ->json('session_token');
    }

    private function pngValido(): string
    {
        return 'data:image/png;base64,' . base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function jpegValido(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0" . str_repeat("\x00", 128));
    }

    /** @return array<int, array<string, mixed>> */
    private function tracos(int $pontos = 60): array
    {
        $lista = [];

        for ($i = 0; $i < $pontos; $i++) {
            $lista[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        return [['points' => $lista]];
    }

    /** @return array<string, mixed> */
    private function assinatura(array $extra = []): array
    {
        return array_merge([
            'signature' => $this->pngValido(),
            'strokes' => $this->tracos(),
            'photo' => $this->jpegValido(),
            'photo_consent' => true,
            'accepted' => true,
            'read_seconds' => 40,
            'scrolled_to_end' => true,
        ], $extra);
    }

    public function test_envio_pelo_painel_manda_o_link_e_guarda_so_o_hash(): void
    {
        $documento = $this->documentoCongelado();

        $token = $this->enviaLink($documento, 'Maria.Nova@Example.com');

        $link = SignatureRemoteLink::sole();
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertSame('maria.nova@example.com', $link->email);
        $this->assertSame('maria.nova@example.com', $documento->signers()->first()->email);
        $this->assertSame('Atendente de Teste', $link->sent_by_name);

        Mail::assertSent(SignatureRemoteLinkMail::class, fn($mail) => $mail->hasTo('maria.nova@example.com'));
        $this->assertStringContainsString('https://reservas.example.com/assinar/' . $token, Mail::sent(SignatureRemoteLinkMail::class)->last()->render());

        // O prazo de horas do balcão foi estendido até o fim do link.
        $this->assertTrue($documento->fresh()->expires_at->gte($link->expires_at));

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_REMOTE_LINK_SENT)->sole();
        $this->assertSame('m***a@example.com', $evento->payload['email']);
        $this->assertStringNotContainsString($token, json_encode(SignatureAuditEvent::all()->pluck('payload')));
    }

    public function test_link_aparece_uma_vez_para_quem_enviou_e_nao_vai_em_claro_para_a_sessao(): void
    {
        $documento = $this->documentoCongelado();
        $atendente = $this->usuarioComPermissoes(['assinatura.documentos']);

        $envio = $this->actingAs($atendente)
            ->post(route('signature-documents.remote-link.store', [$documento, $documento->signers()->first()]), ['email' => 'maria@example.com']);

        preg_match('#/assinar/([A-Za-z0-9]{64})#', Mail::sent(SignatureRemoteLinkMail::class)->last()->render(), $m);
        $url = 'https://reservas.example.com/assinar/' . $m[1];

        // A sessão fica no banco: ali o link vai cifrado.
        $this->assertStringNotContainsString($m[1], json_encode($envio->getSession()->all()));

        $this->actingAs($atendente)->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('data-remote-link-url', false)
            ->assertSee($url, false);

        // Recarregou: o link não volta, mas o cartão continua com a situação dele.
        $this->actingAs($atendente)->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertDontSee($url, false)
            ->assertSee('data-remote-link-status', false);
    }

    public function test_sem_endereco_do_site_nao_envia(): void
    {
        config(['signature.remote.site_url' => '']);
        $documento = $this->documentoCongelado();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.remote-link.store', [$documento, $documento->signers()->first()]), ['email' => 'maria@example.com'])
            ->assertSessionHas('warning');

        Mail::assertNothingSent();
        $this->assertSame(0, SignatureRemoteLink::count());
    }

    public function test_falha_no_smtp_nao_registra_o_link(): void
    {
        $documento = $this->documentoCongelado();

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP fora do ar'));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.remote-link.store', [$documento, $documento->signers()->first()]), ['email' => 'maria@example.com'])
            ->assertSessionHas('error');

        $this->assertSame(0, SignatureRemoteLink::count());
    }

    public function test_api_exige_o_token_proprio_do_site_e_a_origem(): void
    {
        $token = $this->enviaLink($this->documentoCongelado());

        $this->postJson(route('api.assinatura-remota.link'), ['token' => $token])->assertStatus(401);

        // O API_TOKEN das reservas não abre esta API.
        config(['services.api.token' => 'token-das-reservas']);
        $this->withHeaders(['Authorization' => 'Bearer token-das-reservas'])
            ->postJson(route('api.assinatura-remota.link'), ['token' => $token])
            ->assertStatus(401);

        config(['signature.remote.allowed_ips' => ['10.9.9.9']]);
        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $token])->assertStatus(403);

        config(['signature.remote.allowed_ips' => ['127.0.0.0/8']]);
        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $token])->assertOk();
    }

    public function test_fluxo_completo_assina_a_distancia_com_cpf_completo_e_foto(): void
    {
        // O modelo não pede foto e confere só quatro dígitos — no remoto, nada disso vale.
        $documento = $this->documentoCongelado([
            'requires_photo' => false,
            'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
        ]);
        $token = $this->enviaLink($documento);

        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('document.title', $documento->title)
            ->assertJsonPath('requirements.identity', 'cpf_full')
            ->assertJsonPath('requirements.photo', true)
            ->assertJsonPath('identity_attempts_left', 5)
            ->assertDontSee('12345678909');

        $resposta = $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('rules.identity_check', 'full')
            ->assertJsonPath('rules.requires_photo', true)
            ->assertJsonPath('document.pdf_url', null);

        $sessao = $resposta->json('session_token');

        // Os quatro primeiros dígitos não bastam.
        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '1234'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'identity_failed')
            ->assertJsonPath('attempts_left', 4);

        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '123.456.789-09'])->assertOk();

        $this->site($sessao)->get(route('api.assinatura-remota.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Sem foto, não.
        $this->site($sessao)->postJson(route('api.assinatura-remota.sign'), $this->assinatura(['photo' => null]))
            ->assertStatus(422);

        $this->site($sessao)->postJson(route('api.assinatura-remota.sign'), $this->assinatura())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $signatario = $documento->signers()->first();
        $this->assertSame(SignatureSigner::STATUS_SIGNED, $signatario->status);
        $this->assertSame(SignatureDocument::STATUS_SIGNED, $documento->fresh()->status);
        Queue::assertPushed(FinalizeSignatureDocument::class);

        // O IP e o navegador gravados são os de quem assinou, não os do servidor do site.
        $evidencia = SignatureEvidence::sole();
        $this->assertSame('177.10.20.30', $evidencia->ip);
        $this->assertSame('Celular da Maria', $evidencia->user_agent);
        $this->assertNotNull($evidencia->photo_path);

        $this->assertSame(SignatureRemoteLink::STATUS_USED, SignatureRemoteLink::sole()->status);
        $this->assertTrue(SignatureRequest::sole()->isRemote());

        // O link não abre de novo.
        $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'link_used');
    }

    public function test_documento_so_abre_depois_do_cpf(): void
    {
        $documento = $this->documentoCongelado();
        $sessao = $this->abre($this->enviaLink($documento));

        $this->site($sessao)->get(route('api.assinatura-remota.pdf'))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'identity_required');
        $this->site($sessao)->postJson(route('api.assinatura-remota.viewed'), ['read_seconds' => 10])
            ->assertStatus(409);

        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '12345678909'])->assertOk();

        $this->site($sessao)->get(route('api.assinatura-remota.pdf'))->assertOk();
        $this->site($sessao)->getJson(route('api.assinatura-remota.session'))
            ->assertOk()
            ->assertJsonPath('rules.identity_confirmed', true)
            ->assertJsonPath('photo_consent_text', SignatureEvidence::PHOTO_CONSENT_TEXT);
    }

    /** Documento congelado que pede a identidade (obrigatória) e um comprovante (opcional). */
    private function documentoComAnexos(): SignatureDocument
    {
        $rascunho = $this->criaDocumentoDeAssinatura(
            [
                'template' => $this->criaModeloDeAssinatura(['requires_photo' => false]),
                'attachment_requirements' => SignatureAttachmentService::normalize([
                    ['label' => 'Identidade', 'required' => true],
                    ['label' => 'Comprovante', 'required' => false],
                ], 'doc'),
            ],
            ['email' => 'maria@example.com'],
        );

        return app(SignatureDocumentService::class)->freeze($rascunho);
    }

    private function pdf(string $nome = 'rg.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    /** Sessão aberta e CPF confirmado. */
    private function sessaoConfirmada(SignatureDocument $documento): string
    {
        $sessao = $this->abre($this->enviaLink($documento));
        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '12345678909'])->assertOk();

        return $sessao;
    }

    public function test_anexo_obrigatorio_trava_a_assinatura_remota_ate_ser_enviado(): void
    {
        $documento = $this->documentoComAnexos();

        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $this->enviaLink($documento)])
            ->assertOk()
            ->assertJsonPath('requirements.attachments', 2)
            ->assertJsonPath('requirements.attachments_required', 1);

        $sessao = $this->sessaoConfirmada($documento);

        $this->site($sessao)->getJson(route('api.assinatura-remota.session'))
            ->assertOk()
            ->assertJsonPath('attachments.missing_required', ['Identidade'])
            ->assertJsonPath('attachments.items.0.key', 'doc_identidade');

        $this->site($sessao)->postJson(route('api.assinatura-remota.sign'), $this->assinatura())
            ->assertStatus(409)
            ->assertJsonPath('reason', 'attachments_missing');
        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->first()->status);

        $this->site($sessao)->post(route('api.assinatura-remota.attachments.store'), ['key' => 'doc_identidade', 'file' => $this->pdf()])
            ->assertStatus(201)
            ->assertJsonPath('missing_required', [])
            ->assertJsonPath('items.0.files.0.mine', true)
            ->assertJsonPath('items.0.files.0.can_remove', true)
            ->assertJsonPath('items.0.files.0.type', 'pdf');

        $anexo = SignatureAttachment::sole();
        $this->assertSame($documento->signers()->first()->id, $anexo->uploaded_by_signer_id);
        $this->assertNull($anexo->uploaded_by);
        $this->assertSame('Maria de Souza (signatário, pelo link)', $anexo->uploaded_by_name);

        // A trilha registra quem enviou, com o IP de quem assina — sem o nome do arquivo.
        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_ATTACHMENT_ADDED)->sole();
        $this->assertSame(SignatureAuditEvent::ACTOR_KIOSK, $evento->actor_type);
        $this->assertSame('177.10.20.30', $evento->ip);
        $this->assertStringNotContainsString('rg.pdf', json_encode($evento->payload));

        $this->site($sessao)->postJson(route('api.assinatura-remota.sign'), $this->assinatura())->assertOk();

        // Com tudo assinado e o obrigatório enviado, o documento conclui.
        Queue::assertPushed(FinalizeSignatureDocument::class);
    }

    public function test_pessoa_troca_o_proprio_anexo_mas_nao_apaga_o_do_atendente(): void
    {
        $documento = $this->documentoComAnexos();

        // O atendente já enviou o comprovante pela tela do documento.
        $doAtendente = app(SignatureAttachmentService::class)
            ->store($documento, $this->pdf('conta.pdf'), 'doc_comprovante', null, 1, 'Atendente de Teste');

        $sessao = $this->sessaoConfirmada($documento);

        $this->site($sessao)->post(route('api.assinatura-remota.attachments.store'), ['key' => 'doc_identidade', 'file' => $this->pdf()])
            ->assertStatus(201);
        $meu = SignatureAttachment::where('requirement_key', 'doc_identidade')->sole();

        $this->site($sessao)->getJson(route('api.assinatura-remota.attachments'))
            ->assertOk()
            ->assertJsonPath('items.1.files.0.mine', false)
            ->assertJsonPath('items.1.files.0.can_remove', false);

        $this->site($sessao)->deleteJson(route('api.assinatura-remota.attachments.destroy', $doAtendente->id))
            ->assertStatus(403)
            ->assertJsonPath('reason', 'attachment_refused');

        $this->site($sessao)->deleteJson(route('api.assinatura-remota.attachments.destroy', $meu->id))
            ->assertOk()
            ->assertJsonPath('missing_required', ['Identidade']);

        $this->assertNull(SignatureAttachment::find($meu->id));
        $this->assertNotNull(SignatureAttachment::find($doAtendente->id));
        Storage::disk(config('signature.disk'))->assertMissing($meu->path);

        $removido = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_ATTACHMENT_REMOVED)->sole();
        $this->assertSame(SignatureAuditEvent::ACTOR_KIOSK, $removido->actor_type);
    }

    public function test_anexo_pede_cpf_item_pedido_e_tipo_pelo_conteudo(): void
    {
        $documento = $this->documentoComAnexos();
        $sessao = $this->abre($this->enviaLink($documento));

        $this->site($sessao)->post(route('api.assinatura-remota.attachments.store'), ['key' => 'doc_identidade', 'file' => $this->pdf()])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'identity_required');
        $this->site($sessao)->getJson(route('api.assinatura-remota.session'))->assertJsonPath('attachments', null);

        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '12345678909'])->assertOk();

        // Avulso, item que não foi pedido: não.
        $this->site($sessao)->post(route('api.assinatura-remota.attachments.store'), ['key' => 'doc_outra_coisa', 'file' => $this->pdf()])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'attachment_refused');

        // Texto com nome de PDF: o tipo vem do conteúdo.
        $this->site($sessao)->post(route('api.assinatura-remota.attachments.store'), [
            'key' => 'doc_identidade',
            'file' => UploadedFile::fake()->createWithContent('rg.pdf', 'só texto'),
        ])->assertStatus(422);

        $this->assertSame(0, SignatureAttachment::count());
    }

    public function test_tentativas_de_cpf_contam_no_link_entre_aberturas(): void
    {
        $documento = $this->documentoCongelado();
        $token = $this->enviaLink($documento);

        $sessao = $this->abre($token);

        foreach (range(1, 3) as $i) {
            $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '00000000000'])->assertStatus(422);
        }

        // Abrir de novo não devolve tentativa — e derruba a sessão anterior.
        $nova = $this->abre($token);
        $this->site($sessao)->getJson(route('api.assinatura-remota.session'))->assertStatus(419);

        $this->site($nova)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '00000000000'])
            ->assertStatus(422)
            ->assertJsonPath('attempts_left', 1);

        $this->site($nova)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '00000000000'])
            ->assertStatus(423)
            ->assertJsonPath('reason', 'link_locked');

        $this->assertSame(SignatureRemoteLink::STATUS_LOCKED, SignatureRemoteLink::sole()->status);
        $this->assertSame(1, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_REMOTE_LINK_LOCKED)->count());

        $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertStatus(423)
            ->assertJsonPath('reason', 'link_locked');
    }

    public function test_novo_envio_substitui_o_link_anterior(): void
    {
        $documento = $this->documentoCongelado();

        $primeiro = $this->enviaLink($documento);
        $segundo = $this->enviaLink($documento);

        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $primeiro])
            ->assertStatus(410)
            ->assertJsonPath('reason', 'link_superseded');

        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => $segundo])->assertOk();
    }

    public function test_link_vencido_e_token_invalido(): void
    {
        $token = $this->enviaLink($this->documentoCongelado());

        $this->site()->postJson(route('api.assinatura-remota.link'), ['token' => str_repeat('a', 64)])
            ->assertStatus(404)
            ->assertJsonPath('reason', 'invalid_link');

        Carbon::setTestNow(now()->addHours(73));

        $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertStatus(410)
            ->assertJsonPath('reason', 'link_expired');
    }

    public function test_revogar_pelo_painel_derruba_a_sessao_aberta(): void
    {
        $documento = $this->documentoCongelado();
        $token = $this->enviaLink($documento);
        $sessao = $this->abre($token);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->delete(route('signature-documents.remote-link.destroy', [$documento, SignatureRemoteLink::sole()]))
            ->assertSessionHas('success');

        $this->site($sessao)->getJson(route('api.assinatura-remota.session'))->assertStatus(419);
        $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertStatus(410)
            ->assertJsonPath('reason', 'link_revoked');
    }

    public function test_sessao_remota_nao_abre_o_tablet(): void
    {
        $documento = $this->documentoCongelado();
        $sessao = $this->abre($this->enviaLink($documento));

        $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $sessao)
            ->getJson(route('quiosque.session'))
            ->assertStatus(419);
    }

    public function test_recusa_pelo_link_recusa_o_documento(): void
    {
        $documento = $this->documentoCongelado();
        $sessao = $this->abre($this->enviaLink($documento));

        $this->site($sessao)->postJson(route('api.assinatura-remota.refuse'), ['reason' => 'Não concordo'])->assertOk();

        $this->assertSame(SignatureDocument::STATUS_REFUSED, $documento->fresh()->status);
        $this->assertSame(SignatureRemoteLink::STATUS_USED, SignatureRemoteLink::sole()->status);
    }

    public function test_preparar_para_o_govbr_revoga_os_links(): void
    {
        $documento = $this->documentoCongelado();
        $token = $this->enviaLink($documento);

        app(GovbrCheckService::class)->prepare($documento->fresh(), 1, 'Atendente de Teste');

        $this->assertSame(SignatureRemoteLink::STATUS_REVOKED, SignatureRemoteLink::sole()->status);
        $this->site()->postJson(route('api.assinatura-remota.open'), ['token' => $token])
            ->assertStatus(410)
            ->assertJsonPath('reason', 'link_revoked');
    }

    public function test_diretor_nao_recebe_link(): void
    {
        $documento = $this->documentoCongelado();
        $signatario = $documento->signers()->first();
        $signatario->forceFill(['user_id' => 7, 'director_area_id' => 1])->save();

        $this->assertStringContainsString('Diretor', (string) app(RemoteSignatureService::class)->blockReason($signatario->fresh()));
    }

    public function test_manifesto_diz_que_foi_assinatura_remota(): void
    {
        $documento = $this->documentoCongelado();
        $sessao = $this->abre($this->enviaLink($documento));

        $this->site($sessao)->postJson(route('api.assinatura-remota.identity'), ['cpf' => '12345678909'])->assertOk();
        $this->site($sessao)->postJson(route('api.assinatura-remota.sign'), $this->assinatura())->assertOk();

        $html = app(SignatureDocumentRenderer::class)->html($documento->fresh(['template', 'signers']), SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Assinatura remota, pelo link enviado a m***a@example.com', $html);
        $this->assertStringContainsString('Conferência de identidade: CPF completo, digitado por quem abriu o link', $html);
        $this->assertStringContainsString('Link remoto · 177.10.20.30', $html);
    }

    public function test_painel_mostra_o_cartao_so_com_o_site_configurado(): void
    {
        $documento = $this->documentoCongelado();
        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        $this->actingAs($usuario)->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Assinatura remota')
            ->assertSee('Enviar link');

        config(['signature.remote.site_url' => '']);

        $this->actingAs($usuario)->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertDontSee('data-remote-box', false);
    }
}
