<?php

namespace Tests\Feature;

use App\Models\SignatureContract;
use App\Models\SignatureContractVersion;
use App\Models\User;
use App\Services\Signature\Contracts\ContractKioskService;
use App\Services\Signature\Contracts\ContractRecipients;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContractSchema;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Validação de contratos pela Gerência no tablet do balcão (decisão do
 * usuário, 27/10/2026): entrada por matrícula + PIN, só para o setor
 * Gerência; lista, leitura, observação por cláusula e decisão pelo mesmo
 * ContractService da tela web.
 *
 * Usuários de verdade (SharesSqliteWithUserConnection): a entrada confere
 * PIN, situação e setor no banco.
 */
class SignatureContractKioskTest extends TestCase
{
    use CreatesContractSchema;
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
        $this->createContractSchema(false);

        Storage::fake(config('signature.disk'));
        Mail::fake();
        Queue::fake();
        RateLimiter::clear('contratos-gerencia:login:900');

        foreach (['Gerência', 'Compras'] as $setor) {
            if (!DB::table('sectors')->whereRaw('LOWER(name) = ?', [mb_strtolower($setor)])->exists()) {
                DB::table('sectors')->insert(['name' => $setor, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $this->criaPresidente();

        $destinatarios = \Mockery::mock(ContractRecipients::class);
        $destinatarios->shouldReceive('reviewers')->andReturn(['coordenador@example.com']);
        $destinatarios->shouldReceive('author')->andReturn('autora@example.com');
        $this->app->instance(ContractRecipients::class, $destinatarios);

        $diretores = \Mockery::mock(DirectorAreaService::class);
        $diretores->shouldReceive('signerData')->andReturnUsing(fn(int $area, int $user) => [
            'name' => 'Alex Ribeiro da Silva', 'cpf' => '52998224725', 'email' => null, 'phone' => null,
            'member_id' => null, 'user_id' => $user, 'director_area_id' => $area,
            'director_area_name' => 'Presidência', 'director_role' => 'primary',
        ]);
        $this->app->instance(DirectorAreaService::class, $diretores);
    }

    private function usuario(string $nome, string $matricula, string $setor, string $pin = '123456'): User
    {
        $user = $this->makeUser($nome, ['matricula' => $matricula, 'pin' => bcrypt($pin), 'status_id' => 1]);
        $this->joinSector($user, $setor);

        return $user->fresh();
    }

    private function contratoEmValidacao(): SignatureContract
    {
        $servico = app(ContractService::class);
        $contrato = $servico->create($this->sectorId('Compras'), 'Compras', null, null, 7, 'Compradora');
        $contrato->forceFill(['content' => $this->conteudoCompleto()])->save();
        $servico->submit($contrato->fresh(), 'Primeira versão', 7, 'Compradora');

        return $contrato->fresh();
    }

    private function entra(string $matricula = '900', string $pin = '123456')
    {
        return $this->withCredentials()->postJson(route('quiosque.gerencia.login'), ['matricula' => $matricula, 'pin' => $pin]);
    }

    public function test_a_pagina_do_tablet_abre_sem_login_e_o_balcao_mostra_o_botao(): void
    {
        $this->get(route('quiosque.gerencia.index'))->assertOk()->assertSee('Validação de contratos');
        $this->get(route('quiosque.index'))
            ->assertOk()
            ->assertSee('id="abrirGerencia"', false)
            ->assertSee(json_encode(route('quiosque.gerencia.index')), false);
    }

    public function test_so_a_gerencia_entra(): void
    {
        $this->usuario('Gerente', '900', 'Gerência');
        $this->usuario('Compradora', '901', 'Compras');

        $this->entra('900', '000000')->assertStatus(422)->assertJsonPath('error', 'Matrícula ou PIN inválido.');
        $this->entra('901')->assertStatus(422)->assertJsonFragment(['error' => 'Seu usuário não é do setor Gerência: a validação de contratos é da Gerência.']);

        $this->contratoEmValidacao();

        $this->entra('900')
            ->assertOk()
            ->assertCookie(ContractKioskService::COOKIE)
            ->assertJsonPath('user.name', 'Gerente')
            ->assertJsonPath('contracts.0.counterparty', 'A.C.S TRADING LTDA');
    }

    public function test_sem_entrada_nada_responde(): void
    {
        $contrato = $this->contratoEmValidacao();

        $this->getJson(route('quiosque.gerencia.pending'))->assertStatus(401)->assertJsonPath('ended', true);
        $this->getJson(route('quiosque.gerencia.show', ['contract' => $contrato->id]))->assertStatus(401);
        $this->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'approve'])->assertStatus(401);

        $this->assertSame(SignatureContract::STATUS_IN_REVIEW, $contrato->fresh()->status);
    }

    public function test_le_e_devolve_com_observacao_na_clausula(): void
    {
        $gerente = $this->usuario('Gerente', '900', 'Gerência');
        $contrato = $this->contratoEmValidacao();

        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->getJson(route('quiosque.gerencia.show', ['contract' => $contrato->id]))
            ->assertOk()
            ->assertJsonPath('note', 'Primeira versão')
            ->assertJsonPath('clauses.1.uid', 'clvalor001')
            ->assertJsonPath('errors', []);

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), [
                'decision' => 'return',
                'comments' => ['clvalor001' => 'Conferir o valor com a proposta.'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_RETURNED, $contrato->status);
        $this->assertSame('clvalor001', $contrato->comments()->first()->clause_uid);
        // Quem decidiu é quem entrou no tablet.
        $this->assertSame($gerente->id, $contrato->latestVersion()->decided_by);
        $this->assertSame(SignatureContractVersion::DECISION_RETURNED, $contrato->latestVersion()->decision);
    }

    public function test_edita_o_texto_pelo_tablet_e_vira_ajuste_da_gerencia(): void
    {
        $gerente = $this->usuario('Gerente', '900', 'Gerência');
        $contrato = $this->contratoEmValidacao();
        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->getJson(route('quiosque.gerencia.show', ['contract' => $contrato->id]))
            ->assertOk()
            ->assertJsonPath('edit.clauses.0.uid', 'clobjeto01')
            ->assertJsonPath('edit.clauses.0.items.1.level', 2);

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.update', ['contract' => $contrato->id]), [
                'title' => 'Prestação de serviços de manutenção',
                'clauses' => [
                    ['uid' => 'clobjeto01', 'title' => 'Do objeto', 'items' => [
                        ['uid' => 'itobjeto11', 'level' => 1, 'html' => '<p>Manutenção <b>corretiva</b> na subestação.</p><script>x</script>'],
                        ['uid' => null, 'level' => 1, 'html' => 'Item novo do tablet'],
                    ]],
                    // Cláusula que não existe não entra.
                    ['uid' => 'naoexiste1', 'title' => 'Intrusa', 'items' => [['html' => 'x']]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('contract.edit.clauses.0.title', 'Do objeto');

        $conteudo = $contrato->fresh()->content;
        $this->assertSame('Prestação de serviços de manutenção', $conteudo['title']);
        $this->assertCount(3, $conteudo['clauses'], 'cláusulas não somem nem aparecem');
        $this->assertSame('Do objeto', $conteudo['clauses'][0]['title']);
        $this->assertCount(2, $conteudo['clauses'][0]['items']);
        $this->assertStringContainsString('<b>corretiva</b>', $conteudo['clauses'][0]['items'][0]['html']);
        $this->assertStringNotContainsString('script', $conteudo['clauses'][0]['items'][0]['html']);
        $this->assertSame('Valor', $conteudo['clauses'][1]['title'], 'o que não veio fica como estava');

        // Na decisão, o ajuste vira versão da Gerência.
        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'approve'] + $this->vistoDeTeste())
            ->assertOk();

        $contrato = $contrato->fresh();
        $this->assertTrue((bool) $contrato->latestVersion()->by_reviewer);
        $this->assertTrue($contrato->events()->where('event', 'reviewer_edited')->where('actor_id', $gerente->id)->exists());
    }

    public function test_sem_entrada_nao_edita_nem_pede_revisao(): void
    {
        $contrato = $this->contratoEmValidacao();

        $this->postJson(route('quiosque.gerencia.update', ['contract' => $contrato->id]), ['clauses' => [['uid' => 'clobjeto01', 'title' => 'X']]])
            ->assertStatus(401);
        $this->postJson(route('quiosque.gerencia.ai-review', ['contract' => $contrato->id]))->assertStatus(401);
        $this->assertSame('Objeto', $contrato->fresh()->content['clauses'][0]['title']);
    }

    public function test_pede_a_revisao_do_claude_pelo_tablet_com_trechos_anexados(): void
    {
        config(['claude.api_key' => 'sk-ant-teste', 'claude.contract_review.model' => 'claude-haiku-5-5']);
        $claude = \Mockery::mock(\App\Services\Claude\ClaudeClient::class);
        $claude->shouldReceive('isConfigured')->andReturn(true);
        $claude->shouldReceive('structured')->once()
            ->withArgs(fn(...$a) => str_contains($a['prompt'] ?? $a[2] ?? '', '<material titulo="Proposta">')
                && str_contains($a['system'] ?? $a[1] ?? '', 'Revisão jurídica'))
            ->andReturn([
                'data' => [
                    'veredito' => 'ajustes_recomendados',
                    'resumo' => 'Prazo diverge da proposta.',
                    'apontamentos' => [['clausula' => 2, 'trecho' => 'Item 2.1', 'gravidade' => 'alta', 'problema' => 'Valor diferente da proposta.', 'sugestao' => 'Conferir.']],
                    'juridico' => [
                        'risco' => 'alto',
                        'resumo' => 'Sem garantia de execução.',
                        'fraquezas' => [['clausula' => 1, 'ponto' => 'Objeto sem prazo de entrega.', 'sugestao' => 'Definir o prazo.']],
                        'procurar_juridico' => true,
                    ],
                ],
                'model' => 'claude-haiku-5-5', 'input_tokens' => 1, 'output_tokens' => 1,
            ]);
        $this->app->instance(\App\Services\Claude\ClaudeClient::class, $claude);

        $gerente = $this->usuario('Gerente', '900', 'Gerência');
        $contrato = $this->contratoEmValidacao();
        RateLimiter::clear('contract-ai-review:' . $gerente->id);
        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->getJson(route('quiosque.gerencia.show', ['contract' => $contrato->id]))
            ->assertOk()
            ->assertJsonPath('ai.enabled', true)
            ->assertJsonPath('ai.review', null);

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.ai-review', ['contract' => $contrato->id]), [
                'extras' => [['title' => 'Proposta', 'text' => 'Valor de R$ 9.000,00.']],
            ])
            ->assertOk()
            ->assertJsonPath('ai.review.summary', 'Prazo diverge da proposta.')
            ->assertJsonPath('ai.review.extras.0', 'Proposta')
            // O apontamento chega com o uid da cláusula, para virar observação.
            ->assertJsonPath('ai.review.findings.0.uid', 'clvalor001')
            ->assertJsonPath('ai.review.by', 'Gerente')
            // No tablet só entra a Gerência: vem a revisão jurídica.
            ->assertJsonPath('ai.review.legal.risk', 'alto')
            ->assertJsonPath('ai.review.legal.seek_lawyer', true)
            ->assertJsonPath('ai.review.legal.weaknesses.0.uid', 'clobjeto01');

        $this->assertSame($gerente->id, $contrato->aiReviews()->first()->requested_by);
    }

    public function test_aprovar_pelo_tablet_exige_o_visto(): void
    {
        $this->usuario('Gerente', '900', 'Gerência');
        $contrato = $this->contratoEmValidacao();
        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        $this->get(route('quiosque.gerencia.index'))->assertSee('id="faVisto"', false);

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'approve'])
            ->assertStatus(409)
            ->assertJsonFragment(['error' => 'Faça o seu visto no quadro antes de aprovar: ele vai em todas as páginas do contrato.']);

        $this->assertSame(SignatureContract::STATUS_IN_REVIEW, $contrato->fresh()->status);

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'approve'] + $this->vistoDeTeste())
            ->assertOk();

        $contrato = $contrato->fresh();
        $this->assertNotNull(SignatureContractVersion::find($contrato->approved_version_id)->approval_initials_path);
        $this->assertNotNull($contrato->document->approval_initials_path);
    }

    public function test_aprova_pelo_tablet(): void
    {
        $this->usuario('Gerente', '900', 'Gerência');
        $contrato = $this->contratoEmValidacao();

        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'approve'] + $this->vistoDeTeste())
            ->assertOk();

        $contrato = $contrato->fresh();
        $this->assertSame(SignatureContract::STATUS_APPROVED, $contrato->status);
        $this->assertSame('Gerente', $contrato->approved_by_name);
        $this->assertNotNull($contrato->signature_document_id);

        // Outra decisão no mesmo contrato: já não está em validação.
        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->postJson(route('quiosque.gerencia.decide', ['contract' => $contrato->id]), ['decision' => 'return', 'note' => 'x'])
            ->assertStatus(409);
    }

    public function test_quem_sai_da_gerencia_perde_a_entrada(): void
    {
        $gerente = $this->usuario('Gerente', '900', 'Gerência');
        $cookie = $this->entra()->getCookie(ContractKioskService::COOKIE)->getValue();

        DB::table('user_sector')->where('user_id', $gerente->id)->delete();

        $this->withCredentials()->withCookie(ContractKioskService::COOKIE, $cookie)
            ->getJson(route('quiosque.gerencia.pending'))
            ->assertStatus(401);
    }
}
