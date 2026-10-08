<?php

namespace Tests\Feature;

use App\Exceptions\SignatureFormException;
use App\Exceptions\SignatureSessionException;
use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureFieldTypes as T;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * Campos com tipo, perguntas a quem assina e data automática.
 *
 * A garantia que este teste protege é a central do módulo, agora num caminho
 * mais longo: o que a pessoa lê no tablet é o arquivo cujo hash ficou
 * gravado. Com o formulário, o documento é refeito DEPOIS do congelamento —
 * então o que precisa valer é que ele é refeito antes da leitura, que o hash
 * acompanha, que a troca fica na trilha e que nada disso acontece depois que
 * alguém assinou.
 */
class SignatureSigningFormTest extends TestCase
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

    /** Um campo do atendente, dois de quem assina e a data automática. */
    private function modelo(array $attributes = []): SignatureTemplate
    {
        return $this->criaModeloDeAssinatura(array_merge([
            'body_html' => '<p>Uso do espaço [[espaco]]. Contato: [[telefone]]. Atividades: [[atividades]].</p>'
                . '<p>Volta Redonda, [[data_assinatura]].</p>[[assinatura]]',
            'identity_check' => SignatureTemplate::IDENTITY_NONE,
            'requires_photo' => false,
            'variables' => [
                ['key' => 'espaco', 'label' => 'Espaço', 'required' => true, 'type' => T::TEXT],
                [
                    'key' => 'telefone', 'label' => 'Telefone', 'required' => true, 'type' => T::PHONE,
                    'ask_signer' => true, 'question' => 'Qual é o seu telefone?',
                ],
                [
                    'key' => 'atividades', 'label' => 'Atividades', 'required' => false, 'type' => T::CHECKBOX,
                    'ask_signer' => true, 'options' => ['Piscina', 'Academia', 'Quadra'],
                ],
                ['key' => 'data_assinatura', 'label' => 'Data da assinatura', 'type' => T::DATE_SIGNING_LONG],
            ],
        ], $attributes));
    }

    private function congelado(?SignatureTemplate $modelo = null): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura([
            'template' => $modelo ?? $this->modelo(),
            'data' => ['espaco' => 'Piscina'],
        ]));
    }

    /**
     * @return array{document: SignatureDocument, cookie: string, payload: array<string, mixed>}
     */
    private function sessaoAberta(?SignatureDocument $documento = null): array
    {
        $documento ??= $this->congelado();

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

    private function comSessao(string $cookie): self
    {
        return $this->withCredentials()->withCookie(EnsureSignatureKioskSession::COOKIE, $cookie);
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

    public function test_congelar_nao_exige_o_que_e_de_quem_assina_e_guarda_os_marcadores(): void
    {
        $documento = $this->congelado();

        // O atendente resolveu o campo dele; os outros ficam para a assinatura.
        $this->assertStringContainsString('Uso do espaço Piscina.', $documento->body_snapshot);
        $this->assertStringContainsString('[[telefone]]', $documento->body_snapshot);
        $this->assertStringContainsString('[[data_assinatura]]', $documento->body_snapshot);

        // Até lá, lacuna — nunca o marcador cru na frente da pessoa.
        $texto = app(SignatureDocumentRenderer::class)->resolved($documento);

        $this->assertStringNotContainsString('[[telefone]]', $texto);
        $this->assertStringContainsString('Contato: ' . SignatureDocumentRenderer::BLANK, $texto);

        $this->assertTrue($documento->signingFormPending());
    }

    public function test_campo_obrigatorio_do_atendente_continua_barrando_o_congelamento(): void
    {
        $rascunho = $this->criaDocumentoDeAssinatura(['template' => $this->modelo(), 'data' => []]);

        $this->expectExceptionMessage('Faltam dados obrigatórios do modelo: Espaço.');

        app(SignatureDocumentService::class)->freeze($rascunho);
    }

    public function test_abrir_no_tablet_data_o_documento_com_a_data_do_servidor(): void
    {
        Carbon::setTestNow('2026-10-03 10:00:00');

        $documento = $this->congelado();
        $hashDoCongelamento = $documento->original_sha256;

        // Aberto no dia seguinte: a data é a de quando a pessoa está no balcão.
        Carbon::setTestNow('2026-10-04 09:00:00');

        ['document' => $documento] = $this->sessaoAberta($documento);

        $this->assertSame('2026-10-04', $documento->signing_data['data_assinatura']);
        $this->assertStringContainsString(
            'Volta Redonda, 4 de outubro de 2026.',
            app(SignatureDocumentRenderer::class)->resolved($documento),
        );

        // O arquivo mudou, o hash acompanhou, e a troca está na trilha.
        $this->assertNotSame($hashDoCongelamento, $documento->original_sha256);
        $this->assertSame(
            $documento->original_sha256,
            hash('sha256', Storage::disk(config('signature.disk'))->get($documento->original_path)),
        );

        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_SIGNING_DATE_SET)->sole();

        $this->assertSame($hashDoCongelamento, $evento->payload['hash_anterior']);
        $this->assertSame($documento->original_sha256, $evento->payload['hash_novo']);
    }

    public function test_reabrir_no_mesmo_dia_nao_gera_hash_novo(): void
    {
        $documento = $this->congelado();
        $servico = app(SignatureSigningDataService::class);

        $hash = $servico->prepare($documento)->original_sha256;

        $this->assertSame($hash, $servico->prepare($documento->fresh())->original_sha256);
        $this->assertSame(
            1,
            $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_SIGNING_DATE_SET)->count(),
        );
    }

    public function test_tablet_recebe_as_perguntas_do_modelo(): void
    {
        ['payload' => $payload] = $this->sessaoAberta();

        $this->assertFalse($payload['form']['answered']);

        // Só o que é de quem assina: nem o campo do atendente, nem a data automática.
        $this->assertSame(['telefone', 'atividades'], array_column($payload['form']['fields'], 'key'));

        $this->assertSame('Qual é o seu telefone?', $payload['form']['fields'][0]['question']);
        $this->assertTrue($payload['form']['fields'][0]['required']);

        // Sem pergunta escrita, a pergunta é o nome do campo.
        $this->assertSame('Atividades', $payload['form']['fields'][1]['question']);
        $this->assertSame(['Piscina', 'Academia', 'Quadra'], $payload['form']['fields'][1]['options']);
    }

    public function test_modelo_sem_pergunta_nao_manda_formulario(): void
    {
        $modelo = $this->criaModeloDeAssinatura(['identity_check' => SignatureTemplate::IDENTITY_NONE]);

        ['payload' => $payload, 'document' => $documento] = $this->sessaoAberta(
            app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura(['template' => $modelo])),
        );

        $this->assertNull($payload['form']);
        // E nada é refeito: o hash do congelamento é o que vale.
        $this->assertNull($documento->signing_data);
    }

    public function test_respostas_entram_no_documento_antes_da_leitura_e_o_hash_acompanha(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $hashAntes = $documento->original_sha256;

        $resposta = $this->comSessao($cookie)
            ->postJson(route('quiosque.answers', $documento), [
                'answers' => ['telefone' => '(24) 99999-1234', 'atividades' => ['Quadra', 'Piscina']],
            ])
            ->assertOk();

        $documento->refresh();

        // Guardado na forma canônica, escrito no documento na forma de ler.
        $this->assertSame('24999991234', $documento->signing_data['telefone']);
        $this->assertStringContainsString(
            'Contato: (24) 99999-1234. Atividades: Piscina e Quadra.',
            app(SignatureDocumentRenderer::class)->resolved($documento),
        );

        $this->assertNotNull($documento->signing_answered_at);
        $this->assertFalse($documento->signingFormPending());

        // O PDF que o tablet busca em seguida é o do documento respondido.
        $this->assertNotSame($hashAntes, $documento->original_sha256);
        $this->assertSame(
            $documento->original_sha256,
            hash('sha256', Storage::disk(config('signature.disk'))->get($documento->original_path)),
        );
        $this->assertStringContainsString(substr($documento->original_sha256, 0, 12), $resposta->json('document.pdf_url'));

        // O formulário volta respondido — para uma correção, não para assinar às cegas.
        $this->assertTrue($resposta->json('form.answered'));
        $this->assertSame('(24) 99999-1234', $resposta->json('form.fields.0.value'));

        // A trilha diz QUAIS campos, nunca os valores: ela é impressa no manifesto.
        $evento = $documento->auditEvents()->where('event', SignatureAuditEvent::EVENT_FORM_ANSWERED)->sole();

        $this->assertSame(['telefone', 'atividades'], $evento->payload['campos']);
        $this->assertSame($hashAntes, $evento->payload['hash_anterior']);
        $this->assertStringNotContainsString('99999', json_encode($evento->payload));

        // O congelamento do atendente não foi tocado.
        $this->assertStringContainsString('[[telefone]]', $documento->body_snapshot);
    }

    public function test_resposta_invalida_volta_com_o_erro_de_cada_pergunta_e_nao_grava(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $hashAntes = $documento->original_sha256;

        $this->comSessao($cookie)
            ->postJson(route('quiosque.answers', $documento), [
                'answers' => ['telefone' => '9999', 'atividades' => ['Sauna']],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error', 'errors' => ['telefone', 'atividades']]);

        // Obrigatória em branco também é erro — no tablet não existe "depois".
        $this->comSessao($cookie)
            ->postJson(route('quiosque.answers', $documento), ['answers' => ['telefone' => '']])
            ->assertStatus(422)
            ->assertJsonPath('errors.telefone', 'Esta resposta é obrigatória.');

        $documento->refresh();

        $this->assertNull($documento->signing_answered_at);
        $this->assertSame($hashAntes, $documento->original_sha256);
    }

    public function test_corrigir_a_resposta_antes_de_assinar_refaz_o_documento(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->comSessao($cookie)->postJson(route('quiosque.answers', $documento), [
            'answers' => ['telefone' => '24999991234', 'atividades' => ['Piscina']],
        ])->assertOk();

        $this->comSessao($cookie)->postJson(route('quiosque.answers', $documento), [
            'answers' => ['telefone' => '2433334444'],
        ])->assertOk();

        $documento->refresh();

        $this->assertSame('2433334444', $documento->signing_data['telefone']);
        // A opção desmarcada na correção não sobrevive da tentativa anterior.
        $this->assertArrayNotHasKey('atividades', $documento->signing_data);
        // A data automática, sim: ela não é resposta.
        $this->assertArrayHasKey('data_assinatura', $documento->signing_data);
    }

    public function test_nao_se_assina_documento_com_pergunta_sem_resposta(): void
    {
        ['document' => $documento, 'cookie' => $cookie] = $this->sessaoAberta();

        $this->comSessao($cookie)->postJson(route('quiosque.identity', $documento), ['cpf' => '0'])->assertOk();

        $this->comSessao($cookie)
            ->postJson(route('quiosque.sign', $documento), $this->assinatura())
            ->assertStatus(409)
            ->assertJsonPath('error', 'Responda as perguntas do documento antes de assinar.');

        $this->assertSame(SignatureSigner::STATUS_PENDING, $documento->signers()->first()->status);
    }

    public function test_depois_da_primeira_assinatura_as_respostas_nao_mudam_mais(): void
    {
        $rascunho = $this->criaDocumentoDeAssinatura(['template' => $this->modelo(), 'data' => ['espaco' => 'Piscina']]);

        SignatureSigner::create([
            'signature_document_id' => $rascunho->id,
            'name' => 'João Testemunha',
            'cpf' => '98765432100',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        $documento = app(SignatureDocumentService::class)->freeze($rascunho);

        ['cookie' => $cookie] = $this->sessaoAberta($documento);

        $this->comSessao($cookie)->postJson(route('quiosque.answers', $documento), [
            'answers' => ['telefone' => '24999991234'],
        ])->assertOk();

        $this->comSessao($cookie)->postJson(route('quiosque.identity', $documento), ['cpf' => '0'])->assertOk();
        $this->comSessao($cookie)->postJson(route('quiosque.sign', $documento), $this->assinatura())->assertOk();

        $hashAssinado = $documento->fresh()->original_sha256;

        // O segundo signatário abre no dia seguinte: não vê formulário, e o
        // documento — data inclusive — é o que o primeiro leu e assinou.
        Carbon::setTestNow(now()->addDay());

        ['payload' => $payload, 'document' => $documento] = $this->sessaoAberta($documento->fresh());

        $this->assertNull($payload['form']);
        $this->assertSame($hashAssinado, $documento->original_sha256);

        $this->expectException(SignatureSessionException::class);

        app(SignatureSigningDataService::class)->answer($documento, ['telefone' => '2433334444']);
    }

    public function test_resposta_nao_consegue_mexer_na_area_de_assinatura(): void
    {
        $modelo = $this->modelo([
            'body_html' => '<p>Observação: [[obs]]</p>[[assinatura]]',
            'variables' => [
                ['key' => 'obs', 'label' => 'Observação', 'type' => T::TEXT, 'ask_signer' => true],
            ],
        ]);

        $documento = app(SignatureSigningDataService::class)->answer(
            app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura(['template' => $modelo])),
            ['obs' => '[[assinatura]] <b>negrito</b>'],
        );

        $html = app(SignatureDocumentRenderer::class)->html($documento);

        // Uma área de assinatura só — a do modelo. O que a pessoa digitou é texto.
        $this->assertSame(1, substr_count($html, 'class="sig-area"'));
        $this->assertStringContainsString('&lt;b&gt;negrito&lt;/b&gt;', $html);
    }

    public function test_servico_recusa_resposta_invalida_por_campo(): void
    {
        try {
            app(SignatureSigningDataService::class)->answer($this->congelado(), ['telefone' => 'abc']);

            $this->fail('Resposta inválida foi aceita.');
        } catch (SignatureFormException $e) {
            $this->assertSame(['telefone'], array_keys($e->errors));
        }
    }

    public function test_manifesto_diz_de_onde_veio_cada_dado(): void
    {
        $documento = app(SignatureSigningDataService::class)->answer(
            $this->congelado(),
            ['telefone' => '24999991234'],
        );

        $html = app(SignatureDocumentRenderer::class)->html($documento, SignatureDocumentRenderer::MODE_FINAL);

        $this->assertStringContainsString('Dados preenchidos no ato da assinatura', $html);
        $this->assertStringContainsString('Telefone, Atividades', $html);
        $this->assertStringContainsString('Formulário respondido pelo signatário', $html);
    }

    /* ------------------------------------------------------------------
     | Telas do painel
     |------------------------------------------------------------------*/

    public function test_modelo_grava_tipo_pergunta_e_opcoes_de_cada_campo(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo de uso',
                'body_html' => '<p>[[espaco]] [[contato]] [[hoje]]</p>',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'variables' => [
                    [
                        'key' => 'espaco', 'label' => 'Espaço', 'type' => T::RADIO, 'required' => '1',
                        'ask_signer' => '1', 'question' => 'Qual espaço você vai usar?',
                        // Como a tela manda: texto, uma opção por linha.
                        'options' => "Piscina\n\n Quadra \nPiscina",
                    ],
                    [
                        'key' => 'contato', 'label' => 'Contato', 'type' => T::PHONE,
                        // Sobra de quando o campo era de escolha: não é gravada.
                        'options' => "A\nB", 'question' => 'ignorada',
                    ],
                    ['key' => 'hoje', 'label' => 'Hoje', 'type' => T::DATE_SIGNING, 'required' => '1', 'ask_signer' => '1'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        [$espaco, $contato, $hoje] = SignatureTemplate::firstOrFail()->variables;

        $this->assertSame(['Piscina', 'Quadra'], $espaco['options']);
        $this->assertTrue($espaco['ask_signer']);
        $this->assertSame('Qual espaço você vai usar?', $espaco['question']);

        $this->assertSame([], $contato['options']);
        $this->assertFalse($contato['ask_signer']);
        $this->assertNull($contato['question']);

        // Campo automático não é perguntado a ninguém nem pode ficar "em branco".
        $this->assertFalse($hoje['ask_signer']);
        $this->assertFalse($hoje['required']);
    }

    public function test_campo_de_escolha_sem_opcoes_e_recusado(): void
    {
        $this->actingAs($this->usuarioComPermissoes(['assinatura.modelos']))
            ->post(route('signature-templates.store'), [
                'name' => 'Termo',
                'body_html' => '<p>[[espaco]]</p>',
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'variables' => [
                    ['key' => 'espaco', 'label' => 'Espaço', 'type' => T::CHECKBOX, 'options' => 'Piscina'],
                ],
            ])
            ->assertSessionHasErrors('variables.0.options');

        $this->assertSame(0, SignatureTemplate::count());
    }

    public function test_telas_do_modelo_renderizam_com_os_tipos(): void
    {
        $modelo = $this->modelo();
        $usuario = $this->usuarioComPermissoes(['assinatura.modelos', 'assinatura.documentos']);

        $this->actingAs($usuario)->get(route('signature-templates.edit', $modelo))
            ->assertOk()
            ->assertSee('Perguntar a quem assina, no tablet')
            ->assertSee(T::LABELS[T::DATE_SIGNING_LONG]);

        $this->actingAs($usuario)->get(route('signature-templates.show', $modelo))
            ->assertOk()
            ->assertSee('Qual é o seu telefone?');
    }

    public function test_formulario_pede_os_signatarios_antes_dos_dados_e_oferece_os_dados_deles(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>[[nome]] [[cpf]] [[valor]] [[inicio]]</p>',
            'variables' => [
                ['key' => 'nome', 'label' => 'Nome do associado', 'type' => T::TEXT],
                ['key' => 'cpf', 'label' => 'CPF', 'type' => T::CPF],
                ['key' => 'valor', 'label' => 'Valor', 'type' => T::MONEY],
                ['key' => 'inicio', 'label' => 'Início', 'type' => T::DATE],
            ],
        ]);

        $html = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertSee('Preencher com os dados dos signatários')
            ->getContent();

        // Quem assina vem primeiro: é de lá que saem os dados dos campos.
        $this->assertLessThan(
            strpos($html, 'name="data[nome]"'),
            strpos($html, 'name="signers[0][name]"'),
            'Os signatários precisam vir antes dos dados do documento.',
        );

        // A tela é dividida em passos, um cartão por vez, nesta ordem; os botões da
        // tela é que ganham "Voltar" e "Continuar".
        preg_match_all('/data-step="([^"]+)"/', $html, $passos);
        $this->assertSame(['Documento', 'Signatários', 'Dados do documento', 'Anexos'], $passos[1]);
        $this->assertStringContainsString('data-step-actions', $html);

        // Texto aceita qualquer dado do signatário; CPF, só CPF; valor e data, nenhum.
        $this->assertStringContainsString('data-signer-fill="data_nome" data-fill-kinds="name,cpf,email,phone"', $html);
        $this->assertStringContainsString('data-signer-fill="data_cpf" data-fill-kinds="cpf"', $html);
        $this->assertStringNotContainsString('data-signer-fill="data_valor"', $html);
        $this->assertStringNotContainsString('data-signer-fill="data_inicio"', $html);
    }

    public function test_titulo_padrao_leva_o_nome_do_primeiro_signatario(): void
    {
        $modelo = $this->criaModeloDeAssinatura(['name' => 'Termo de uso da piscina']);
        $servico = app(SignatureDocumentService::class);

        $signatarios = [
            ['name' => 'Maria de Souza', 'cpf' => '12345678909'],
            ['name' => 'João Pereira', 'cpf' => '98765432100'],
        ];

        // O formulário manda o nome do modelo, que é como o campo vem preenchido.
        $this->assertSame(
            'Termo de uso da piscina - Maria de Souza',
            $servico->create($modelo, ['title' => 'Termo de uso da piscina'], $signatarios)->title,
        );

        // Em branco, idem.
        $this->assertSame(
            'Termo de uso da piscina - Maria de Souza',
            $servico->create($modelo, [], $signatarios)->title,
        );

        // Título digitado é respeitado como está.
        $this->assertSame(
            'Piscina — temporada 2026',
            $servico->create($modelo, ['title' => 'Piscina — temporada 2026'], $signatarios)->title,
        );

        // Pela tela, do mesmo jeito.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.store'), [
                'signature_template_id' => $modelo->id,
                'title' => 'Termo de uso da piscina',
                'signers' => [['name' => 'Ana Lima', 'cpf' => '123.456.789-09']],
            ])
            ->assertRedirect();

        $this->assertSame('Termo de uso da piscina - Ana Lima', SignatureDocument::latest('id')->firstOrFail()->title);

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertSee('o nome do primeiro signatário é acrescentado');
    }

    public function test_atendente_so_preenche_o_que_e_dele_e_o_valor_e_conferido_pelo_tipo(): void
    {
        $modelo = $this->criaModeloDeAssinatura([
            'body_html' => '<p>[[cpf_responsavel]] [[valor]] [[telefone]]</p>',
            'variables' => [
                ['key' => 'cpf_responsavel', 'label' => 'CPF do responsável', 'type' => T::CPF],
                ['key' => 'valor', 'label' => 'Valor', 'type' => T::MONEY],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => T::PHONE, 'ask_signer' => true],
            ],
        ]);

        $usuario = $this->usuarioComPermissoes(['assinatura.documentos']);

        $envio = fn(array $data) => [
            'signature_template_id' => $modelo->id,
            'data' => $data,
            'signers' => [['name' => 'Maria de Souza', 'cpf' => '123.456.789-09']],
        ];

        // A tela oferece o campo do atendente e avisa do que não é dele.
        $this->actingAs($usuario)->get(route('signature-documents.create', ['template' => $modelo->id]))
            ->assertOk()
            ->assertSee('name="data[cpf_responsavel]"', false)
            ->assertDontSee('name="data[telefone]"', false)
            ->assertSee('Quem assina responde no tablet');

        $this->actingAs($usuario)
            ->post(route('signature-documents.store'), $envio(['cpf_responsavel' => '123.456.789-00']))
            ->assertSessionHasErrors('data.cpf_responsavel');

        $this->actingAs($usuario)
            ->post(route('signature-documents.store'), $envio([
                'cpf_responsavel' => '123.456.789-09',
                'valor' => '1.500,00',
                // Mandado à força pelo formulário: não é do atendente, não entra.
                'telefone' => '24999991234',
            ]))
            ->assertSessionHasNoErrors();

        $documento = SignatureDocument::firstOrFail();

        $this->assertSame(['cpf_responsavel' => '12345678909', 'valor' => '1500.00'], $documento->data);
        $this->assertStringContainsString(
            '123.456.789-09 R$ 1.500,00 ' . SignatureDocumentRenderer::BLANK,
            app(SignatureDocumentRenderer::class)->resolved($documento),
        );

        // Reabrir para corrigir mostra o valor como a pessoa o digitaria.
        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.edit', $documento))
            ->assertOk()
            ->assertSee('value="1.500,00"', false)
            ->assertSee('value="123.456.789-09"', false);
    }
}
