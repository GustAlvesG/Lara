<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Guides\UserGuides;
use Illuminate\Support\Facades\File;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Busca nos guias do usuário (GET /api/guias/search), consumida pela Lara
 * (IA). Guias de teste numa pasta própria; usuários de verdade (as permissões
 * passam por setor), no SQLite compartilhado com a conexão do User.
 */
class GuideSearchTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private string $dir;
    private User $ti;
    private User $semAcesso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();

        $this->dir = storage_path('framework/testing/guias-' . uniqid());
        File::ensureDirectoryExists($this->dir);

        File::put($this->dir . '/siv.md', <<<'MD'
---
modulo: SIV
palavras: placa, carro
---

# SIV — guia de uso

O SIV identifica quem está no carro.

## Buscar uma placa
<!-- telas: parking.search -->

Na tela SIV › Busca, digite a placa.

### Placa não encontrada

Confira a digitação.

## Cadastrar um veículo da frota
<!-- telas: fleet.vehicles -->

Use a tela de veículos para a inscrição de um veículo novo.

## Dúvidas comuns

### O que é o SIV?

Sistema de identificação de veículos.
MD);

        File::put($this->dir . '/geral.md', <<<'MD'
---
modulo: Geral
---

# Geral

## Buscar uma placa

Na tela, digite a placa.
MD);

        File::put($this->dir . '/ingressos.md', <<<'MD'
---
modulo: Ingressos
palavras: evento
---

# Ingressos

## Emitir cortesia
<!-- telas: tickets.index -->

Na tela Ingressos › Eventos, abra o evento.
MD);

        $this->app->instance(UserGuides::class, new UserGuides($this->dir));

        $this->ti = $this->makeUser('Pessoa da TI');
        $this->joinSector($this->ti, 'TI');

        $this->semAcesso = $this->makeUser('Pessoa sem setor');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function buscar(string $q, User $user, string $extra = ''): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(
            '/api/guias/search?q=' . urlencode($q) . '&user_id=portal_' . $user->id . $extra,
            ['Authorization' => 'Bearer ' . config('services.api.token')]
        );
    }

    public function test_exige_token_e_usuario(): void
    {
        $this->getJson('/api/guias/search?q=placa&user_id=' . $this->ti->id)->assertStatus(401);

        $this->getJson('/api/guias/search?q=placa', ['Authorization' => 'Bearer ' . config('services.api.token')])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Informe o usuário que pergunta (user_id).');

        $this->getJson('/api/guias/search?q=placa&user_id=999999', ['Authorization' => 'Bearer ' . config('services.api.token')])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Usuário não encontrado.');
    }

    public function test_quem_alcanca_a_tela_recebe_o_link(): void
    {
        $response = $this->buscar('como busco uma placa?', $this->ti)->assertOk();

        $response->assertJsonPath('data.0.secao', 'SIV — guia de uso › Buscar uma placa')
            ->assertJsonPath('data.0.modulo', 'SIV')
            ->assertJsonPath('data.0.tem_acesso', true)
            ->assertJsonPath('data.0.telas.0.nome', 'SIV › Busca')
            ->assertJsonPath('data.0.telas.0.url', route('parking.search'))
            ->assertJsonPath('data.0.precisa_de', []);

        $this->assertStringEndsWith('/docs/guias/siv#buscar-uma-placa', $response->json('data.0.url'));
    }

    public function test_quem_nao_alcanca_ve_a_secao_e_o_que_pedir(): void
    {
        $this->buscar('buscar placa', $this->semAcesso)
            ->assertOk()
            ->assertJsonPath('data.0.secao', 'SIV — guia de uso › Buscar uma placa')
            ->assertJsonPath('data.0.tem_acesso', false)
            ->assertJsonPath('data.0.telas', [])
            ->assertJsonPath('data.0.precisa_de.0', 'SIV › Busca (permissão: SIV › Busca de placas)');
    }

    public function test_requisitos_estruturados_para_quem_nao_alcanca(): void
    {
        $item = $this->buscar('buscar placa', $this->semAcesso)->json('data.0');

        $this->assertSame([[
            'tela' => 'SIV › Busca',
            'permissoes' => ['SIV › Busca de placas'],
            'basta_uma' => false,
            'regra' => null,
        ]], $item['requisitos']);

        // Quem alcança não recebe requisito nenhum.
        $this->assertSame([], $this->buscar('buscar placa', $this->ti)->json('data.0.requisitos'));
    }

    public function test_tela_com_habilidade_composta_lista_as_permissoes_que_bastam(): void
    {
        // Ingressos › Eventos abre com qualquer permissão do módulo
        // (Permissions::ANY_OF['acessar-ingressos']): a tela nunca vem sozinha.
        $item = $this->buscar('emitir cortesia', $this->semAcesso)->assertOk()->json('data.0');

        $this->assertFalse($item['tem_acesso']);
        $this->assertStringStartsWith('Ingressos › Eventos (uma destas permissões: Ingressos › ', $item['precisa_de'][0]);

        $requisito = $item['requisitos'][0];
        $this->assertSame('Ingressos › Eventos', $requisito['tela']);
        $this->assertTrue($requisito['basta_uma']);
        $this->assertCount(count(\App\Authorization\Permissions::ANY_OF['acessar-ingressos']), $requisito['permissoes']);
        $this->assertContains('Ingressos › Check-in na entrada do evento', $requisito['permissoes']);
    }

    public function test_subsecao_herda_a_tela_e_secao_sem_anotacao_vale_o_modulo(): void
    {
        $response = $this->buscar('placa encontrada', $this->semAcesso)->assertOk();
        $naoEncontrada = collect($response->json('data'))->firstWhere('secao', 'SIV — guia de uso › Buscar uma placa › Placa não encontrada');
        $this->assertFalse($naoEncontrada['tem_acesso']);

        // "O que é o SIV?" não cita tela: vale o módulo inteiro, sem lista de links.
        $duvida = collect($this->buscar('o que é o SIV', $this->ti)->json('data'))
            ->firstWhere('secao', 'SIV — guia de uso › Dúvidas comuns › O que é o SIV?');
        $this->assertTrue($duvida['tem_acesso']);
        $this->assertSame([], $duvida['telas']);

        $duvidaSemAcesso = collect($this->buscar('o que é o SIV', $this->semAcesso)->json('data'))
            ->firstWhere('secao', 'SIV — guia de uso › Dúvidas comuns › O que é o SIV?');
        $this->assertFalse($duvidaSemAcesso['tem_acesso']);
        $this->assertContains('SIV › Frota (permissão: SIV › Frota (saída e retorno))', $duvidaSemAcesso['precisa_de']);
    }

    public function test_guia_fora_do_menu_vale_para_todos(): void
    {
        $geral = collect($this->buscar('placa', $this->semAcesso)->json('data'))->firstWhere('guia', 'Geral');

        $this->assertTrue($geral['tem_acesso']);
    }

    public function test_sem_acento_e_no_plural_tambem_acha(): void
    {
        $this->buscar('inscrições de veiculos', $this->ti)
            ->assertOk()
            ->assertJsonPath('data.0.secao', 'SIV — guia de uso › Cadastrar um veículo da frota');
    }

    public function test_modulo_da_tela_atual_vem_primeiro(): void
    {
        // Sem contexto, o SIV ganha (palavras: placa).
        $this->buscar('placa', $this->ti)->assertJsonPath('data.0.guia', 'SIV — guia de uso');

        // Na tela de outro módulo, a seção dele sobe.
        $this->buscar('placa', $this->ti, '&modulo=Geral')
            ->assertJsonPath('modulo', 'Geral')
            ->assertJsonPath('data.0.guia', 'Geral');
    }

    public function test_limite_de_resultados(): void
    {
        $this->buscar('placa', $this->ti, '&limit=1')->assertJsonPath('total', 1);
    }

    public function test_pergunta_sem_termo_util_nao_devolve_nada(): void
    {
        $this->buscar('como eu faço no sistema?', $this->ti)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
