<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureKioskDevice;
use App\Models\SignatureMinorAuthorization;
use App\Models\SignatureMinorTerm;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\MinorTerms\KioskDeviceService;
use App\Services\Signature\MinorTerms\MinorTermDirectoryUnavailable;
use App\Services\Signature\MinorTerms\MinorTermMemberDirectory;
use Database\Seeders\MinorTermTemplateSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O autoatendimento do Termo de Menores, pelo HTTP, como o tablet faz:
 * pareamento → título → responsável → CPF → menor → documento → assinatura
 * pelas rotas do quiosque de sempre.
 *
 * O MultiClubes é simulado (MinorTermMemberDirectory no container): a suíte
 * roda em SQLite e não alcança o SQL Server.
 */
class SignatureMinorTermKioskTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    private const TITULO = '12345';
    private const CPF_JOAO = '52998224725';
    private const CPF_MARIA = '11144477735';

    private SignatureMinorTerm $termo;

    /** @var array<string, mixed>|null */
    private ?array $titulo = null;

    private bool $multiclubesFora = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();

        (new MinorTermTemplateSeeder())->run();

        $this->termo = SignatureMinorTerm::create([
            'name' => 'OKTOBERPET 2026',
            'signature_template_id' => SignatureTemplate::where('name', MinorTermTemplateSeeder::NAME)->value('id'),
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ]);

        $this->titulo = $this->tituloPadrao();

        $teste = $this;
        $this->app->instance(MinorTermMemberDirectory::class, new class ($teste) extends MinorTermMemberDirectory {
            public function __construct(private $teste)
            {
            }

            public function title(string $code): ?array
            {
                return $this->teste->multiclubes($code);
            }
        });
    }

    /** O MultiClubes simulado. */
    public function multiclubes(string $code): ?array
    {
        if ($this->multiclubesFora) {
            throw new MinorTermDirectoryUnavailable('fora');
        }

        return $code === self::TITULO ? $this->titulo : null;
    }

    /** @return array<string, mixed> */
    private function tituloPadrao(): array
    {
        $pessoa = fn(int $id, string $nome, ?Carbon $nascimento, string $cpf = '', ?string $rg = null, ?string $email = null) => [
            'id' => $id, 'name' => $nome, 'birth_date' => $nascimento, 'cpf' => $cpf, 'rg' => $rg,
            'email' => $email, 'titular' => $id === 1,
        ];

        return [
            'code' => self::TITULO,
            'address' => 'Rua das Flores, 10 - Centro - Volta Redonda/RJ',
            'people' => [
                $pessoa(1, 'João Carlos da Silva', now()->subYears(45), self::CPF_JOAO, '1234567', 'joao@exemplo.com.br'),
                // Sem e-mail e sem RG no cadastro.
                $pessoa(2, 'Maria de Souza Silva', now()->subYears(40), self::CPF_MARIA),
                // Menor, sem CPF nem RG no cadastro.
                $pessoa(3, 'Pedro Henrique Silva', now()->subYears(12)),
                // Menor sem sobrenome em comum com João.
                $pessoa(4, 'Ana Clara Pereira', now()->subYears(10), '', 'RG-4'),
                // Faz 18 amanhã: ainda é menor.
                $pessoa(5, 'Lucas Silva', now()->subYears(18)->addDay(), '39053344705', 'RG-5'),
                // Fez 18 hoje: já é adulto.
                $pessoa(6, 'Bruno dos Santos Silva', now()->subYears(18), '74682489070'),
            ],
        ];
    }

    /** Pareia como o tablet: o usuário gera o QR, o tablet lê. Devolve o cookie. */
    private function pareia(): string
    {
        $pareamento = app(KioskDeviceService::class)->startPairing('Tablet da entrada', 1, 'Atendente de Teste');

        $resposta = $this->postJson(route('quiosque.menores.pair'), ['payload' => $pareamento['payload']]);

        $resposta->assertOk()->assertJsonPath('paired', true)->assertJsonPath('term.name', 'OKTOBERPET 2026');

        return $resposta->getCookie(KioskDeviceService::COOKIE)->getValue();
    }

    private function comTablet(string $cookie, ?string $sessao = null): self
    {
        $teste = $this->withCredentials()->withCookie(KioskDeviceService::COOKIE, $cookie);

        return $sessao === null ? $teste : $teste->withCookie(EnsureSignatureKioskSession::COOKIE, $sessao);
    }

    /** Título + responsável João confirmado. Devolve a lista de menores. */
    private function confirmaJoao(string $tablet): array
    {
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();

        return $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 1, 'cpf' => '529.982.247-25'])
            ->assertOk()
            ->json();
    }

    private function pngValido(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    private function jpegValido(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0" . str_repeat("\x00", 128));
    }

    /** @return array<int, array<string, mixed>> */
    private function tracos(): array
    {
        $pontos = [];

        for ($i = 0; $i < 60; $i++) {
            $pontos[] = ['x' => $i, 'y' => $i % 20, 't' => $i * 12];
        }

        return [['points' => $pontos]];
    }

    public function test_tela_do_tablet_no_modo_menores_renderiza(): void
    {
        $html = $this->get(route('quiosque.menores.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Termo de Menores', $html);
        $this->assertStringContainsString('Apresente ao Representante do Clube', $html);
        $this->assertStringContainsString(SignatureKioskDevice::PAIRING_PREFIX, $html);
        $this->assertStringContainsString('"pareado":false', $html);
        $this->assertDoesNotMatchRegularExpression('/@(verbatim|endverbatim|include|if|endif)\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/\b(localStorage|sessionStorage|indexedDB)\s*[.\[]/i', $html);
    }

    public function test_tela_do_balcao_nao_leva_o_modo_menores(): void
    {
        $html = $this->get(route('quiosque.index'))->assertOk()->getContent();

        $this->assertStringContainsString('menores: null', $html);
        $this->assertStringNotContainsString('id="tela-verde"', $html);
        $this->assertStringNotContainsString('id="tela-m-inicio"', $html);
    }

    public function test_tablet_sem_pareamento_nao_alcanca_o_autoatendimento(): void
    {
        $this->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])
            ->assertStatus(401)
            ->assertJsonPath('device_unpaired', true);
    }

    public function test_qr_de_pareamento_e_de_uso_unico_e_vence(): void
    {
        $pareamento = app(KioskDeviceService::class)->startPairing(null, 1, 'Atendente');

        $this->postJson(route('quiosque.menores.pair'), ['payload' => $pareamento['payload']])->assertOk();
        $this->postJson(route('quiosque.menores.pair'), ['payload' => $pareamento['payload']])->assertStatus(422);

        $outro = app(KioskDeviceService::class)->startPairing(null, 1, 'Atendente');
        $this->travelTo(now()->addSeconds(config('signature.minor_terms.pairing_ttl_seconds') + 1));
        $this->postJson(route('quiosque.menores.pair'), ['payload' => $outro['payload']])->assertStatus(422);
    }

    public function test_pareamento_vence_em_12_horas_e_pode_ser_revogado(): void
    {
        $tablet = $this->pareia();

        $this->comTablet($tablet)->getJson(route('quiosque.menores.state'))->assertOk();

        $this->travelTo(now()->addHours(12)->addMinute());
        $this->comTablet($tablet)->getJson(route('quiosque.menores.state'))->assertStatus(401);

        $this->travelBack();
        $outro = $this->pareia();
        app(KioskDeviceService::class)->revoke(SignatureKioskDevice::latest('id')->first(), 'Coordenação');
        $this->comTablet($outro)->getJson(route('quiosque.menores.state'))->assertStatus(401);
    }

    public function test_titulo_lista_so_os_maiores_de_idade(): void
    {
        $tablet = $this->pareia();

        $resposta = $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])
            ->assertOk();

        $this->assertSame(
            ['João Carlos da Silva', 'Maria de Souza Silva', 'Bruno dos Santos Silva'],
            array_column($resposta->json('adults'), 'name'),
        );
        // Nem CPF nem nascimento vão à tela.
        $this->assertStringNotContainsString(self::CPF_JOAO, $resposta->getContent());
    }

    public function test_titulo_inexistente_e_multiclubes_fora_dao_mensagens_diferentes(): void
    {
        $tablet = $this->pareia();

        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => '99999'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Título não encontrado ou inativo. Confira o número e tente de novo.');

        $this->multiclubesFora = true;

        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])
            ->assertStatus(503);
    }

    public function test_sem_termo_vigente_o_tablet_avisa(): void
    {
        $tablet = $this->pareia();
        $this->termo->update(['active' => false]);

        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])
            ->assertStatus(409)
            ->assertJsonPath('restart', true);
    }

    public function test_cpf_errado_conta_tentativas_e_encerra(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();

        for ($i = 1; $i <= 4; $i++) {
            $this->comTablet($tablet)
                ->postJson(route('quiosque.menores.responsible'), ['person' => 1, 'cpf' => '00000000000'])
                ->assertStatus(422);
        }

        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 1, 'cpf' => '00000000000'])
            ->assertStatus(429)
            ->assertJsonPath('restart', true);

        // Recomeçar pelo título não zera o contador.
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();
        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 1, 'cpf' => self::CPF_JOAO])
            ->assertStatus(429);
    }

    public function test_menor_nao_pode_ser_responsavel(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();

        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 5, 'cpf' => '39053344705'])
            ->assertStatus(422);
    }

    public function test_menores_sao_os_do_titulo_com_sobrenome_em_comum(): void
    {
        $tablet = $this->pareia();
        $lista = $this->confirmaJoao($tablet);

        $this->assertSame(['Pedro Henrique Silva', 'Lucas Silva'], array_column($lista['minors'], 'name'));
        $this->assertSame(['cpf', 'rg'], $lista['minors'][0]['missing']);
        $this->assertSame([], $lista['minors'][1]['missing']);
        $this->assertSame([], $lista['responsible']['missing']);
        $this->assertSame(17, $lista['minors'][1]['age']);
        $this->assertFalse($lista['minors'][0]['authorized']);
    }

    public function test_responsavel_sem_email_e_rg_aparece_com_o_que_falta(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();

        $lista = $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 2, 'cpf' => self::CPF_MARIA])
            ->assertOk()
            ->json();

        $this->assertSame(['email', 'rg'], $lista['responsible']['missing']);
    }

    public function test_fluxo_completo_gera_documento_assina_e_marca_autorizado(): void
    {
        $tablet = $this->pareia();
        $this->confirmaJoao($tablet);

        $resposta = $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 3, 'minor_cpf' => '390.533.447-05'])
            ->assertOk()
            ->assertJsonPath('already_authorized', false)
            ->assertJsonPath('rules.identity_confirmed', true)
            ->assertJsonPath('signer.has_email', true);

        $sessao = $resposta->getCookie(EnsureSignatureKioskSession::COOKIE)->getValue();
        $documento = SignatureDocument::findOrFail($resposta->json('document.id'));

        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->status);
        $this->assertSame('Autoatendimento — Tablet da entrada', $documento->created_by_name);
        $this->assertSame('Pedro Henrique Silva', $documento->data['nome_menor']);
        $this->assertSame('390.533.447-05', $documento->data['cpf_menor']);
        // RG do menor pulado.
        $this->assertSame('não informado', $documento->data['rg_menor']);
        $this->assertSame('529.982.247-25', $documento->data['cpf_responsavel']);
        $this->assertSame('Rua das Flores, 10 - Centro - Volta Redonda/RJ', $documento->data['endereco_responsavel']);
        $this->assertSame('12', $documento->data['idade_menor']);
        $this->assertStringContainsString('Pedro Henrique Silva', $documento->body_snapshot);

        $signatario = $documento->signers()->first();
        $this->assertSame(SignatureSigner::ROLE_GUARDIAN, $signatario->role);
        $this->assertSame('joao@exemplo.com.br', $signatario->email);

        $this->assertTrue(SignatureAuditEvent::where('signature_document_id', $documento->id)
            ->where('event', SignatureAuditEvent::EVENT_SELF_SERVICE)->exists());

        // Daqui em diante, o quiosque de sempre — sem a etapa de identidade.
        $this->comTablet($tablet, $sessao)
            ->postJson(route('quiosque.sign', $documento), [
                'signature' => $this->pngValido(),
                'strokes' => $this->tracos(),
                'photo' => $this->jpegValido(),
                'photo_consent' => true,
                'accepted' => true,
                'wants_copy' => true,
            ])
            ->assertOk();

        $this->assertSame(SignatureDocument::STATUS_SIGNED, $documento->fresh()->status);
        $this->assertTrue((bool) $signatario->fresh()->wants_copy);

        $lista = $this->comTablet($tablet)->getJson(route('quiosque.menores.minors'))->assertOk()->json();
        $this->assertTrue($lista['minors'][0]['authorized']);

        // Já autorizado: não gera outro documento.
        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 3])
            ->assertOk()
            ->assertJsonPath('already_authorized', true);

        $this->assertSame(1, SignatureMinorAuthorization::count());

        // O histórico (para quando a tela verde já saiu) mostra o cartão e a foto.
        $autorizacao = SignatureMinorAuthorization::firstOrFail();
        $portaria = $this->usuarioComPermissoes([\App\Authorization\Permissions::ASSINATURA_TERMO_MENORES_HISTORICO]);

        $this->actingAs($portaria)->get(route('minor-terms.history'))
            ->assertOk()
            ->assertSee('Pedro Henrique Silva')
            ->assertSee('12 anos')
            ->assertSee('João Carlos da Silva')
            ->assertSee(route('minor-terms.history.photo', $autorizacao));

        $this->actingAs($portaria)->get(route('minor-terms.history.photo', $autorizacao))->assertOk();

        $semPermissao = $this->usuarioComPermissoes([\App\Authorization\Permissions::ASSINATURA_TERMO_MENORES_PAREAR]);
        $this->actingAs($semPermissao)->get(route('minor-terms.history.photo', $autorizacao))->assertForbidden();
    }

    public function test_menor_de_outro_sobrenome_e_recusado(): void
    {
        $tablet = $this->pareia();
        $this->confirmaJoao($tablet);

        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 4])
            ->assertStatus(422);

        $this->assertSame(0, SignatureDocument::count());
    }

    public function test_dado_digitado_invalido_e_recusado(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();
        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 2, 'cpf' => self::CPF_MARIA])
            ->assertOk();

        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 3, 'responsible_email' => 'nao-e-email'])
            ->assertStatus(422);

        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 3, 'minor_cpf' => '11111111111'])
            ->assertStatus(422);

        $this->assertSame(0, SignatureDocument::count());
    }

    public function test_email_pulado_segue_sem_via(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();
        $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.responsible'), ['person' => 2, 'cpf' => self::CPF_MARIA])
            ->assertOk();

        $resposta = $this->comTablet($tablet)
            ->postJson(route('quiosque.menores.document'), ['minor' => 3])
            ->assertOk()
            ->assertJsonPath('signer.has_email', false);

        $documento = SignatureDocument::findOrFail($resposta->json('document.id'));
        $this->assertSame('não informado', $documento->data['e_mail_responsavel']);
        $this->assertSame('não informado', $documento->data['rg_responsavel']);
    }

    public function test_documento_abandonado_e_cancelado_ao_recomecar_o_mesmo_menor(): void
    {
        $tablet = $this->pareia();
        $this->confirmaJoao($tablet);

        $primeiro = $this->comTablet($tablet)->postJson(route('quiosque.menores.document'), ['minor' => 5])->json('document.id');
        $segundo = $this->comTablet($tablet)->postJson(route('quiosque.menores.document'), ['minor' => 5])->json('document.id');

        $this->assertNotSame($primeiro, $segundo);
        $this->assertSame(SignatureDocument::STATUS_CANCELED, SignatureDocument::find($primeiro)->status);
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, SignatureDocument::find($segundo)->status);
    }

    public function test_sem_responsavel_confirmado_nao_ha_lista_nem_documento(): void
    {
        $tablet = $this->pareia();
        $this->comTablet($tablet)->postJson(route('quiosque.menores.title'), ['title' => self::TITULO])->assertOk();

        $this->comTablet($tablet)->getJson(route('quiosque.menores.minors'))->assertStatus(409);
        $this->comTablet($tablet)->postJson(route('quiosque.menores.document'), ['minor' => 3])->assertStatus(409);
    }

    public function test_concluir_esquece_o_atendimento(): void
    {
        $tablet = $this->pareia();
        $this->confirmaJoao($tablet);

        $this->comTablet($tablet)->postJson(route('quiosque.menores.end'))->assertOk();

        $this->comTablet($tablet)->getJson(route('quiosque.menores.minors'))
            ->assertStatus(409)
            ->assertJsonPath('restart', true);
    }

    public function test_termo_de_menores_fica_fora_da_revisao(): void
    {
        $tablet = $this->pareia();
        $this->confirmaJoao($tablet);

        $id = $this->comTablet($tablet)->postJson(route('quiosque.menores.document'), ['minor' => 5])->json('document.id');
        $documento = SignatureDocument::findOrFail($id);
        $documento->forceFill(['status' => SignatureDocument::STATUS_FINALIZED, 'finalized_at' => now()->subDays(3)])->save();

        $this->assertFalse($documento->fresh()->awaitsReview());
        $this->assertSame('Não se aplica', $documento->fresh()->reviewStatusLabel());
        $this->assertSame(
            'Termo de menores não passa pela revisão interna.',
            app(\App\Services\Signature\SignatureReviewService::class)->blockReason($documento->fresh(), 99, true),
        );
    }
}
