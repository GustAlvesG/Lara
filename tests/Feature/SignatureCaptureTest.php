<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Jobs\FinalizeSignatureDocument;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureEvidence;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O ato de assinar, do jeito que o tablet faz: conferir identidade, aceitar,
 * desenhar e ser fotografado.
 *
 * O que está sob teste aqui é o que o tablet NÃO pode decidir sozinho — a
 * conferência do CPF, a recusa de um traço que não é traço, o tipo real dos
 * arquivos e o fato de a gravação ser uma transação só.
 */
class SignatureCaptureTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private SignatureRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));

        /*
         | A fila é `sync` na suíte: sem isto, o job de finalização rodaria
         | dentro de cada assinatura e levaria o documento de `signed` para
         | `finalized`. O que está sob teste aqui é a CAPTURA — a finalização
         | tem o seu próprio teste. O que interessa saber daqui é que o job foi
         | despachado, e isso é verificado abaixo.
         */
        Queue::fake();

        $this->requests = app(SignatureRequestService::class);
    }

    /** PNG de verdade, 1x1, para os testes que precisam de bytes válidos. */
    private function pngValido(): string
    {
        return 'data:image/png;base64,' . base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function jpegValido(): string
    {
        // Cabeçalho JPEG + conteúdo mínimo: o que importa aqui são os bytes
        // iniciais, que é por onde o servidor confere o tipo.
        return 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0" . str_repeat("\x00", 128));
    }

    /** Traço com pontos suficientes para não ser um toque. */
    private function tracos(int $pontos = 60): array
    {
        $lista = [];

        for ($i = 0; $i < $pontos; $i++) {
            $lista[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        return [['points' => $lista]];
    }

    /**
     * @return array{document: SignatureDocument, cookie: string}
     */
    private function sessaoAberta(array $modelo = []): array
    {
        $template = $this->criaModeloDeAssinatura($modelo);
        $documento = app(SignatureDocumentService::class)
            ->freeze($this->criaDocumentoDeAssinatura(['template' => $template]));

        $liberacao = $this->requests->issue($documento->signers()->first());

        $resposta = $this->postJson(route('quiosque.consume'), [
            'payload' => SignatureRequest::qrPayload($liberacao['token']),
        ]);

        return [
            'document' => $documento,
            'cookie' => $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue(),
        ];
    }

    private function comSessao(string $cookie): self
    {
        return $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
    }

    private function confirmaIdentidade(string $cookie, SignatureDocument $documento, string $cpf = '1234'): void
    {
        $this->comSessao($cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => $cpf])
            ->assertOk();
    }

    public function test_identidade_confere_os_quatro_primeiros_digitos_no_servidor(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->comSessao($cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => '1234'])
            ->assertOk();

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED,
        ]);
    }

    public function test_identidade_errada_nao_revela_o_cpf_cadastrado(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $resposta = $this->comSessao($cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => '9999'])
            ->assertStatus(422);

        $this->assertStringNotContainsString('12345678909', $resposta->getContent());
        $this->assertStringNotContainsString('1234', $resposta->json('error'));

        $this->assertDatabaseHas('signature_audit_events', [
            'signature_document_id' => $documento->id,
            'event' => SignatureAuditEvent::EVENT_IDENTITY_FAILED,
        ]);
    }

    /** Quatro dígitos não resistem a chutes ilimitados — daí o teto. */
    public function test_tentativas_de_identidade_tem_teto_e_encerram_a_sessao(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        for ($i = 0; $i < 5; $i++) {
            $this->comSessao($cookie)
                ->postJson(route('quiosque.identity', $documento), ['cpf' => '9999'])
                ->assertStatus(422);
        }

        $this->comSessao($cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => '9999'])
            ->assertStatus(429);

        // A sessão morreu junto: nem o CPF certo serve mais, e o tablet volta
        // à tela de espera (419) em vez de continuar oferecendo tentativas.
        $this->comSessao($cookie)
            ->postJson(route('quiosque.identity', $documento), ['cpf' => '1234'])
            ->assertStatus(419);
    }

    public function test_documento_assinado_diz_quem_o_gerou_e_quem_gerou_o_qr_code(): void
    {
        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);
        $usuario->name = 'Carla Atendente';

        $modelo = $this->criaModeloDeAssinatura([
            'identity_check' => \App\Models\SignatureTemplate::IDENTITY_NONE,
            'requires_photo' => false,
        ]);

        // Quem cria o documento pela tela...
        $this->actingAs($usuario)
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
            ])
            ->assertRedirect();

        $documento = app(SignatureDocumentService::class)->freeze(SignatureDocument::latest('id')->firstOrFail());
        $signatario = $documento->signers()->first();

        $this->assertSame('Carla Atendente', $documento->created_by_name);

        // ...e quem gera o QR Code — aqui, outra pessoa — ficam no documento, pelo nome.
        $outro = $this->usuarioComPermissoes(['assinatura.documentos']);
        $outro->name = 'Paulo do Balcão';

        $this->actingAs($outro)
            ->postJson(route('signature-documents.release', [$documento, $signatario]))
            ->assertCreated();

        $liberacao = $signatario->requests()->firstOrFail();

        $this->assertSame('Paulo do Balcão', $liberacao->created_by_name);

        // Na trilha, os dois eventos levam o nome.
        $eventos = $documento->auditEvents()->get()->keyBy('event');

        $this->assertSame('Carla Atendente', $eventos[SignatureAuditEvent::EVENT_CREATED]->payload['gerado_por']);
        $this->assertSame('Paulo do Balcão', $eventos[SignatureAuditEvent::EVENT_QR_ISSUED]->payload['gerado_por']);

        // Assinado por aquele QR Code, o manifesto do documento cita os dois.
        $liberacao->forceFill(['status' => SignatureRequest::STATUS_COMPLETED])->save();

        $manifesto = app(\App\Services\Signature\SignatureDocumentRenderer::class)
            ->html($documento->fresh(), \App\Services\Signature\SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Documento gerado por: Carla Atendente', $manifesto);
        $this->assertStringContainsString('QR Code gerado por: Paulo do Balcão', $manifesto);

        // E a tela do documento também.
        $this->actingAs($usuario)
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('Carla Atendente')
            ->assertSee('QR Code gerado por Paulo do Balcão');
    }

    public function test_foto_so_entra_com_a_autorizacao_de_quem_assina(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->confirmaIdentidade($cookie, $documento);

        $envio = [
            'signature' => $this->pngValido(),
            'strokes' => $this->tracos(),
            'photo' => $this->jpegValido(),
            'accepted' => true,
        ];

        // Sem marcar a autorização, a assinatura não entra — nem a foto é guardada.
        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $envio)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'É preciso autorizar a captura da imagem para assinar.']);

        $signatario = $documento->signers()->first();

        $this->assertNull($signatario->evidence);
        $this->assertNotSame(SignatureSigner::STATUS_SIGNED, $signatario->fresh()->status);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $envio + ['photo_consent' => true])
            ->assertOk();

        $evidencia = $signatario->fresh()->evidence;

        // Fica o fato e o TEXTO que a pessoa leu.
        $this->assertTrue($evidencia->photo_consent);
        $this->assertSame(SignatureEvidence::PHOTO_CONSENT_TEXT, $evidencia->photo_consent_text);
        $this->assertStringContainsString('Clube dos Funcionários da CSN', $evidencia->photo_consent_text);
        $this->assertStringContainsString('tempo indeterminado', $evidencia->photo_consent_text);

        // E vai para o manifesto do documento assinado.
        $manifesto = app(\App\Services\Signature\SignatureDocumentRenderer::class)
            ->html($documento->fresh(), \App\Services\Signature\SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Autorização da captura da imagem: sim', $manifesto);

        // A tela do tablet traz a caixa de marcação, e o texto dela é o mesmo da
        // evidência. O corpo da página não passa pelo Blade: o texto chega pela
        // configuração do script — expressão do Blade ali apareceria crua na tela.
        $tablet = $this->get(route('quiosque.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="fotoCheck"', $tablet);
        $this->assertStringContainsString('textoAutorizacaoFoto: ' . json_encode(SignatureEvidence::PHOTO_CONSENT_TEXT), $tablet);
        $this->assertStringNotContainsString('{{', $tablet);
    }

    public function test_assinatura_completa_grava_evidencia_e_fecha_o_documento(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
                'read_seconds' => 73,
                'scrolled_to_end' => true,
                'viewport' => ['w' => 800, 'h' => 1280, 'orientation' => 'portrait'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $documento->refresh();
        $signatario = $documento->signers()->first();

        $this->assertSame(SignatureDocument::STATUS_SIGNED, $documento->status);
        $this->assertSame(SignatureSigner::STATUS_SIGNED, $signatario->status);

        // O PDF final é montado em fila, fora da transação: gerar PDF leva
        // segundos e a pessoa está no balcão.
        Queue::assertPushed(FinalizeSignatureDocument::class);

        $evidencia = $signatario->evidence;

        $this->assertNotNull($evidencia);
        $this->assertTrue($evidencia->accepted);
        $this->assertSame(73, $evidencia->read_seconds);
        $this->assertTrue($evidencia->scrolled_to_end);
        $this->assertNotNull($evidencia->server_signed_at);
        $this->assertSame(60, $evidencia->strokePoints());

        Storage::disk(config('signature.disk'))->assertExists($evidencia->signature_path);
        Storage::disk(config('signature.disk'))->assertExists($evidencia->photo_path);

        // A sessão acabou junto com o atendimento.
        $this->comSessao($cookie)->getJson(route('quiosque.session'))->assertStatus(419);
    }

    /**
     * O tablet confere a sessão a cada 5 segundos. Com o contador do throttle
     * dividido entre as rotas, um minuto de leitura já estourava o limite de
     * "assinar" (10/min), e a pessoa via 429 ao salvar.
     */
    public function test_conferir_a_sessao_nao_gasta_o_limite_de_assinar(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta(['requires_photo' => false]);

        $this->confirmaIdentidade($cookie, $documento);

        // Um minuto e meio lendo: 18 batimentos.
        for ($i = 0; $i < 18; $i++) {
            $this->comSessao($cookie)->getJson(route('quiosque.session'))->assertOk();
        }

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'accepted' => true,
            ])
            ->assertOk();
    }

    /** Cada rota segue com o SEU limite: o de assinar continua valendo. */
    public function test_limite_de_cada_rota_continua_valendo(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        for ($i = 0; $i < 10; $i++) {
            $this->comSessao($cookie)->postJson(route('quiosque.sign', $documento), ['accepted' => true]);
        }

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), ['accepted' => true])
            ->assertStatus(429);

        // E a conferência da sessão, que tem o seu, não foi afetada.
        $this->comSessao($cookie)->getJson(route('quiosque.session'))->assertOk();
    }

    /**
     * O disco `local` não lança exceção ao falhar (sem permissão na pasta, por
     * exemplo). A gravação que falha tem de recusar a assinatura com uma
     * mensagem — não registrá-la sem o arquivo.
     */
    public function test_evidencia_que_nao_grava_recusa_a_assinatura(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->confirmaIdentidade($cookie, $documento);

        $nome = config('signature.disk');
        $real = Storage::disk($nome);
        $disco = \Mockery::mock($real);
        $disco->shouldReceive('put')->andReturnUsing(
            fn(string $caminho, $bytes) => str_ends_with($caminho, '.jpg') ? false : $real->put($caminho, $bytes),
        );
        Storage::set($nome, $disco);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
            ])
            ->assertStatus(500)
            ->assertJsonPath('error', 'Não foi possível gravar a assinatura no servidor. Chame o atendente.');

        $signatario = $documento->signers()->first();

        $this->assertSame(SignatureSigner::STATUS_PENDING, $signatario->status);
        $this->assertNull($signatario->evidence);
        // O traço que chegou a ser gravado não fica órfão no disco.
        $this->assertSame([], $real->allFiles(config('signature.paths.signatures')));
        Queue::assertNotPushed(FinalizeSignatureDocument::class);
    }

    public function test_assinatura_sem_confirmar_identidade_e_recusada(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
            ])
            ->assertStatus(409);

        $this->assertSame(
            SignatureSigner::STATUS_PENDING,
            $documento->signers()->first()->status,
        );
    }

    public function test_assinatura_vazia_e_recusada(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => [],
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'A assinatura ficou muito curta. Assine novamente no espaço indicado.');
    }

    /** Um toque na tela não é assinatura. */
    public function test_traco_com_poucos_pontos_e_recusado(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(3),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
            ])
            ->assertStatus(422);
    }

    public function test_aceite_e_obrigatorio(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => false,
            ])
            ->assertStatus(422);
    }

    /**
     * O cabeçalho do data URL é escrito por quem envia. O tipo é conferido
     * pelos BYTES — a mesma trava do cadastro de foto de freelancer.
     */
    public function test_conteudo_que_nao_e_imagem_e_recusado_apesar_do_cabecalho(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => 'data:image/png;base64,' . base64_encode('<?php echo "não sou imagem";'),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(), 'photo_consent' => true,
                'accepted' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'O arquivo enviado não é uma imagem válida.');

        $this->assertSame(0, \App\Models\SignatureEvidence::count());
    }

    public function test_foto_nao_e_exigida_quando_o_modelo_dispensa(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta(['requires_photo' => false]);
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'accepted' => true,
            ])
            ->assertOk();

        $this->assertNull($documento->signers()->first()->evidence->photo_path);
    }

    public function test_foto_e_exigida_quando_o_modelo_pede(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta(['requires_photo' => true]);
        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'accepted' => true,
            ])
            ->assertStatus(422);
    }

    /**
     * Falha de rede depois de a assinatura entrar: o tablet reenvia. Para quem
     * está no balcão o ato aconteceu — recusar mandaria assinar de novo o que
     * já está assinado.
     */
    public function test_reenvio_apos_falha_de_rede_nao_duplica_a_assinatura(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();
        $this->confirmaIdentidade($cookie, $documento);

        $payload = [
            'signature' => $this->pngValido(),
            'strokes' => $this->tracos(),
            'photo' => $this->jpegValido(), 'photo_consent' => true,
            'accepted' => true,
        ];

        $this->comSessao($cookie)->postJson(route('quiosque.sign', $documento), $payload)->assertOk();

        // A sessão já foi encerrada pela primeira gravação; o reenvio bate no
        // middleware, que é o comportamento correto — o tablet mostra a tela
        // de sucesso que já recebeu.
        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $payload)
            ->assertStatus(419);

        $this->assertSame(1, \App\Models\SignatureEvidence::count());
    }

    /**
     * Com mais de um signatário, o documento continua aguardando: cada um tem
     * o seu QR, na ordem.
     */
    public function test_documento_com_dois_signatarios_so_fecha_no_ultimo(): void
    {
        $template = $this->criaModeloDeAssinatura(['requires_photo' => false]);
        $documento = $this->criaDocumentoDeAssinatura(['template' => $template]);

        SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'João Testemunha',
            'cpf' => '98765432100',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        app(SignatureDocumentService::class)->freeze($documento);

        foreach ([['1234', 1], ['9876', 2]] as [$cpf, $posicao]) {
            $signatario = $documento->signers()->where('position', $posicao)->first();
            $liberacao = $this->requests->issue($signatario);

            $cookie = $this->postJson(route('quiosque.consume'), [
                'payload' => SignatureRequest::qrPayload($liberacao['token']),
            ])->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();

            $this->confirmaIdentidade($cookie, $documento, $cpf);

            $this->comSessao($cookie)
                ->postJson(route('quiosque.sign', $documento), [
                    'signature' => $this->pngValido(),
                    'strokes' => $this->tracos(),
                    'accepted' => true,
                ])
                ->assertOk();

            $documento->refresh();

            $this->assertSame(
                $posicao === 1
                    ? SignatureDocument::STATUS_AWAITING_SIGNATURE
                    : SignatureDocument::STATUS_SIGNED,
                $documento->status,
            );
        }
    }
}
