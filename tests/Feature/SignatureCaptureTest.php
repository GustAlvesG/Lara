<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
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

    private SignatureRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));

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

    public function test_assinatura_completa_grava_evidencia_e_fecha_o_documento(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->confirmaIdentidade($cookie, $documento);

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(),
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

    public function test_assinatura_sem_confirmar_identidade_e_recusada(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(),
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
                'photo' => $this->jpegValido(),
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
                'photo' => $this->jpegValido(),
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
                'photo' => $this->jpegValido(),
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
                'photo' => $this->jpegValido(),
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
            'photo' => $this->jpegValido(),
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
