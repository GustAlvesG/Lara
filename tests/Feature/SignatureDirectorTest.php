<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Mail\SignatureDirectorPendingMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDirectorArea;
use App\Models\SignatureDirectorAreaHolder;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\Directors\DirectorException;
use App\Services\Signature\Directors\DirectorKioskService;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Assinatura Diretoria: áreas com titular e substitutos, o diretor escolhido
 * no documento (dados do cadastro, nunca do formulário), o prazo e o aviso
 * por e-mail no congelamento, e a entrada do diretor no tablet com matrícula
 * e PIN — sem CPF, com foto sempre.
 *
 * Usuários de verdade (o diretor é consultado por setor, matrícula e PIN):
 * a conexão do User aponta para o mesmo SQLite da suíte.
 */
class SignatureDirectorTest extends TestCase
{
    use CreatesSignatureSchema;
    use MigratesAccessSchema;
    use MocksSignatureUser;
    use SharesSqliteWithUserConnection;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const JPEG_HEAD = "\xFF\xD8\xFF\xE0";

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
        Mail::fake();
        RateLimiter::clear('assinatura-diretoria:login:777');
    }

    private function diretor(string $nome, string $matricula, string $cpf, string $pin = '123456', bool $setor = true): User
    {
        $user = $this->makeUser($nome, [
            'matricula' => $matricula,
            'cpf' => $cpf,
            'pin' => bcrypt($pin),
            'status_id' => 1,
        ]);

        if ($setor) {
            $this->joinSector($user, User::DIRECTORS_SECTOR);
        }

        return $user->fresh();
    }

    private function areaCom(User $titular, ?User $substituto = null): SignatureDirectorArea
    {
        $servico = app(DirectorAreaService::class);
        $area = $servico->createArea('Diretoria Financeira', null);

        $servico->assign($area, $titular, SignatureDirectorAreaHolder::ROLE_PRIMARY);

        if ($substituto) {
            $servico->assign($area, $substituto, SignatureDirectorAreaHolder::ROLE_SUBSTITUTE);
        }

        return $area;
    }

    /** Documento congelado com Maria (sócia) e o diretor escolhido. */
    private function documentoComDiretor(SignatureDirectorArea $area, User $diretor, array $modelo = []): SignatureDocument
    {
        $documento = $this->criaDocumentoDeAssinatura(
            ['template' => $this->criaModeloDeAssinatura(array_merge(['requires_photo' => false], $modelo))],
        );

        SignatureSigner::create(array_merge(
            ['signature_document_id' => $documento->id, 'position' => 2],
            app(DirectorAreaService::class)->signerData($area->id, $diretor->id),
        ));

        return app(SignatureDocumentService::class)->freeze($documento->fresh());
    }

    private function entra(string $matricula = '777', string $pin = '123456')
    {
        return $this->withCredentials()->postJson(route('quiosque.diretoria.login'), [
            'matricula' => $matricula,
            'pin' => $pin,
        ]);
    }

    private function cookieDoDiretor($resposta): string
    {
        return $resposta->getCookie(DirectorKioskService::COOKIE)->getValue();
    }

    /* ------------------------------------------------------------------
     | Áreas e mandatos
     |------------------------------------------------------------------*/

    public function test_titular_novo_encerra_o_anterior_e_substitutos_sao_varios(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $bia = $this->diretor('Bia Diretora', '778', '52998224725');
        $caio = $this->diretor('Caio Diretor', '779', '11144477735');
        $servico = app(DirectorAreaService::class);

        $area = $this->areaCom($ana, $caio);
        $servico->assign($area, $bia, SignatureDirectorAreaHolder::ROLE_PRIMARY);

        $vigentes = $area->currentHolders()->get();

        $this->assertSame([$bia->id, $caio->id], $vigentes->pluck('user_id')->all());
        $this->assertSame($bia->id, $area->primaryHolder()->user_id);
        $this->assertNotNull(SignatureDirectorAreaHolder::where('user_id', $ana->id)->sole()->ended_at);
    }

    public function test_so_entra_na_area_quem_e_do_setor_diretoria_com_matricula(): void
    {
        $area = app(DirectorAreaService::class)->createArea('Diretoria Social', null);
        $fora = $this->diretor('Fulano Atendente', '800', '12345678909', setor: false);

        $this->expectException(DirectorException::class);
        $this->expectExceptionMessage('não é do setor');

        app(DirectorAreaService::class)->assign($area, $fora, SignatureDirectorAreaHolder::ROLE_PRIMARY);
    }

    public function test_tela_da_diretoria_pede_a_permissao(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-directors.index'))
            ->assertForbidden();

        $this->areaCom($this->diretor('Ana Diretora', '777', '12345678909'));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.diretoria']))
            ->get(route('signature-directors.index'))
            ->assertOk()
            ->assertSee('Diretoria Financeira')
            ->assertSee('Ana Diretora');
    }

    /* ------------------------------------------------------------------
     | Documento
     |------------------------------------------------------------------*/

    public function test_diretor_escolhido_tem_os_dados_do_cadastro_e_nao_do_formulario(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $area = $this->areaCom($ana);
        $modelo = $this->criaModeloDeAssinatura();

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'signers' => [
                    ['name' => 'Nome Inventado', 'cpf' => '52998224725', 'director' => $area->id . ':' . $ana->id],
                    // user_id e área solto, sem escolha válida, não vale.
                    ['name' => 'Maria de Souza', 'cpf' => '11144477735', 'user_id' => $ana->id, 'director_area_id' => $area->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        [$diretor, $maria] = SignatureDocument::sole()->signers()->get()->all();

        $this->assertSame('Ana Diretora', $diretor->name);
        $this->assertSame('12345678909', $diretor->cpf);
        $this->assertTrue($diretor->isDirector());
        $this->assertSame('Diretoria Financeira', $diretor->director_area_name);
        $this->assertSame(SignatureDirectorAreaHolder::ROLE_PRIMARY, $diretor->director_role);

        $this->assertFalse($maria->isDirector());
        $this->assertNull($maria->user_id);
    }

    public function test_parte_do_modelo_ligada_a_area_ja_vem_com_o_titular(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $caio = $this->diretor('Caio Diretor', '779', '11144477735');
        $area = $this->areaCom($ana, $caio);

        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>Contrato.</p>[[assinatura:clube]][[assinatura:socio]]',
            'parties' => [
                ['key' => 'clube', 'label' => 'Clube', 'director_area_id' => $area->id],
                ['key' => 'socio', 'label' => 'Sócio'],
            ],
        ]);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertSee('Diretor do clube')
            ->assertSee('Caio Diretor — Substituto')
            ->assertSee('value="' . $area->id . ':' . $ana->id . '" selected', false);
    }

    public function test_quem_nao_responde_pela_area_nao_pode_ser_escolhido(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $bia = $this->diretor('Bia Diretora', '778', '52998224725');
        $area = $this->areaCom($ana);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $this->criaModeloDeAssinatura()->id,
                'signers' => [['name' => 'x', 'cpf' => '', 'director' => $area->id . ':' . $bia->id]],
            ])
            ->assertSessionHasErrors('signers.0.director');

        $this->assertSame(0, SignatureDocument::count());
    }

    public function test_substituto_sai_identificado_no_rotulo(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $caio = $this->diretor('Caio Diretor', '779', '11144477735');
        $area = $this->areaCom($ana, $caio);

        $signatario = new SignatureSigner(app(DirectorAreaService::class)->signerData($area->id, $caio->id));

        $this->assertSame('Diretoria Financeira (substituto)', $signatario->capacityLabel());
    }

    public function test_congelar_com_diretor_da_prazo_em_dias_e_avisa_por_email(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $documento = $this->documentoComDiretor($this->areaCom($ana), $ana);

        $this->assertTrue($documento->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        Mail::assertSent(SignatureDirectorPendingMail::class, fn($m) => $m->hasTo($ana->email));

        $diretor = $documento->signers()->whereNotNull('director_area_id')->sole();
        $this->assertNotNull($diretor->director_notified_at);
        $this->assertSame(1, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_DIRECTOR_NOTIFIED)->count());
    }

    public function test_filtro_aguardando_diretoria(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $this->documentoComDiretor($this->areaCom($ana), $ana);
        $semDiretor = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura(['title' => 'Sem diretor nenhum']));

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.index', ['status' => 'diretoria']))
            ->assertOk()
            ->assertSee('Termo de responsabilidade — Piscina')
            ->assertDontSee($semDiretor->title);
    }

    /* ------------------------------------------------------------------
     | Tablet
     |------------------------------------------------------------------*/

    public function test_pin_errado_e_quem_nao_e_diretor_nao_entram(): void
    {
        $this->diretor('Ana Diretora', '777', '12345678909');
        $this->diretor('Fulano Atendente', '800', '52998224725', setor: false);

        $this->entra('777', '000000')->assertStatus(401)->assertJson(['error' => 'Matrícula ou PIN inválido.']);
        $this->entra('999', '123456')->assertStatus(401)->assertJson(['error' => 'Matrícula ou PIN inválido.']);
        $this->entra('800', '123456')->assertForbidden();
    }

    public function test_tentativas_de_pin_sao_limitadas_por_matricula(): void
    {
        $this->diretor('Ana Diretora', '777', '12345678909');

        for ($i = 0; $i < (int) config('signature.directors.max_login_attempts'); $i++) {
            $this->entra('777', '000000')->assertStatus(401);
        }

        // Nem o PIN certo entra enquanto durar o bloqueio.
        $this->entra('777', '123456')->assertStatus(429);
    }

    public function test_lista_tem_so_os_documentos_pendentes_do_diretor_e_fora_do_gov_br(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $bia = $this->diretor('Bia Diretora', '778', '52998224725');
        $area = $this->areaCom($ana, $bia);

        $daAna = $this->documentoComDiretor($area, $ana);
        $this->documentoComDiretor($area, $bia);
        $noGovbr = $this->documentoComDiretor($area, $ana);
        $noGovbr->forceFill(['govbr_sent_at' => now()])->save();

        $resposta = $this->entra()->assertOk()->assertCookie(DirectorKioskService::COOKIE);

        $this->assertSame(
            [$daAna->signers()->whereNotNull('director_area_id')->value('id')],
            array_column($resposta->json('documents'), 'signer_id'),
        );
        $this->assertSame('Ana Diretora', $resposta->json('director.name'));
    }

    public function test_diretor_abre_assina_sem_cpf_e_com_foto_obrigatoria(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $documento = $this->documentoComDiretor($this->areaCom($ana), $ana, ['identity_check' => 'full']);
        $signatario = $documento->signers()->whereNotNull('director_area_id')->sole();

        $diretor = $this->cookieDoDiretor($this->entra());

        $aberto = $this->withCredentials()->withCookie(DirectorKioskService::COOKIE, $diretor)
            ->postJson(route('quiosque.diretoria.open', $signatario->id))
            ->assertOk()
            ->assertJsonPath('rules.identity_confirmed', true)
            // O modelo não pede foto; para o diretor ela é sempre exigida.
            ->assertJsonPath('rules.requires_photo', true);

        $liberacao = SignatureRequest::where('signature_signer_id', $signatario->id)->sole();
        $this->assertSame(SignatureRequest::IDENTITY_METHOD_DIRECTOR_PIN, $liberacao->identity_method);
        $this->assertNotNull($liberacao->identity_confirmed_at);

        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED)->sole();
        $this->assertSame(['modo' => 'matricula_pin', 'matricula' => '777'], $evento->payload);

        $sessao = $aberto->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();
        $assinar = fn(array $extra) => $this->withCredentials()
            ->withCookie(EnsureSignatureKioskSession::COOKIE, $sessao)
            ->postJson(route('quiosque.sign', $documento), array_merge([
                'signature' => self::PNG,
                'strokes' => [['points' => array_fill(0, 40, ['x' => 1, 'y' => 1, 't' => 0])]],
                'accepted' => true,
            ], $extra));

        // Sem foto, não assina — mesmo com o modelo dispensando.
        $assinar([])->assertStatus(422);

        $foto = 'data:image/jpeg;base64,' . base64_encode(self::JPEG_HEAD . str_repeat('x', 200));
        $assinar(['photo' => $foto, 'photo_consent' => true])->assertOk();

        $this->assertSame(SignatureSigner::STATUS_SIGNED, $signatario->fresh()->status);
        $this->assertNotNull($signatario->fresh()->evidence->photo_path);
    }

    public function test_um_diretor_nao_abre_o_documento_de_outro(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $bia = $this->diretor('Bia Diretora', '778', '52998224725');
        $area = $this->areaCom($ana, $bia);
        $daBia = $this->documentoComDiretor($area, $bia)->signers()->whereNotNull('director_area_id')->sole();

        $this->withCredentials()->withCookie(DirectorKioskService::COOKIE, $this->cookieDoDiretor($this->entra()))
            ->postJson(route('quiosque.diretoria.open', $daBia->id))
            ->assertStatus(409);

        $this->assertSame(0, SignatureRequest::count());
    }

    /** Diretor assina só pela entrada dele (matrícula + PIN): o QR do atendente é recusado. */
    public function test_diretor_nao_recebe_qr_do_atendente(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $documento = $this->documentoComDiretor($this->areaCom($ana), $ana);
        $diretor = $documento->signers()->whereNotNull('director_area_id')->sole();

        $this->assertStringContainsString('matrícula e o PIN', (string) $diretor->releaseBlockReason());
        $this->assertNull($diretor->releaseBlockReason(directorLogin: true));

        $this->expectException(\App\Exceptions\SignatureDocumentLockedException::class);

        app(\App\Services\Signature\SignatureRequestService::class)->issue($diretor, 1, 'Atendente');
    }

    public function test_painel_nao_oferece_qr_para_o_diretor(): void
    {
        $ana = $this->diretor('Ana Diretora', '777', '12345678909');
        $documento = $this->documentoComDiretor($this->areaCom($ana), $ana);

        $resposta = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', $documento))
            ->assertOk()
            ->assertSee('data-release-directors', false);

        // Só a sócia aparece para gerar QR.
        $this->assertStringNotContainsString(
            'data-signer="' . $documento->signers()->whereNotNull('director_area_id')->value('id') . '"',
            $resposta->getContent(),
        );
    }

    public function test_tempo_de_inatividade_da_lista_vem_da_configuracao(): void
    {
        config(['signature.directors.idle_seconds' => 45]);

        $this->get(route('quiosque.index'))
            ->assertOk()
            ->assertSee('"ociosoSegundos":45', false);
    }

    public function test_sem_entrada_valida_a_lista_responde_encerrada(): void
    {
        $this->withCredentials()->withCookie(DirectorKioskService::COOKIE, 'inventado')
            ->getJson(route('quiosque.diretoria.pending'))
            ->assertStatus(401)
            ->assertJson(['director_ended' => true]);
    }

    public function test_sair_encerra_a_entrada(): void
    {
        $this->diretor('Ana Diretora', '777', '12345678909');
        $cookie = $this->cookieDoDiretor($this->entra());

        $this->withCredentials()->withCookie(DirectorKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.diretoria.logout'))->assertOk();

        $this->withCredentials()->withCookie(DirectorKioskService::COOKIE, $cookie)
            ->getJson(route('quiosque.diretoria.pending'))->assertStatus(401);
    }

    public function test_tablet_do_balcao_oferece_a_entrada_da_diretoria(): void
    {
        $this->get(route('quiosque.index'))
            ->assertOk()
            ->assertSee('id="abrirDiretoria"', false)
            ->assertSee('id="tela-d-entrar"', false)
            ->assertSee(json_encode(route('quiosque.diretoria.login')), false);

        // O tablet do Termo de Menores não tem a entrada da diretoria.
        $this->get(route('quiosque.menores.index'))
            ->assertOk()
            ->assertDontSee('id="tela-d-entrar"', false);
    }
}
