<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\UserAccess;
use App\Models\User;
use App\View\AreaColor;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/**
 * Os componentes da identidade visual (rebrand "Módulos", grená): o que cada
 * um precisa garantir além da aparência — busca que preserva filtros, placa
 * normalizada, área desconhecida que não quebra, imagem que cai no
 * substituto em vez de serviço externo.
 *
 * Não toca banco. O catálogo usa usuário mock (o User está preso à conexão
 * mysql), como em NavigationMenuTest.
 */
class ComponentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_placa_normaliza_e_usa_a_faixa_grena(): void
    {
        $html = (string) $this->blade('<x-plate plate="rkt-4f 21" />');

        $this->assertStringContainsString('RKT4F21', $html);
        $this->assertStringContainsString('bg-plate-band', $html);
        $this->assertStringContainsString('BRASIL', $html);
    }

    public function test_pill_leva_icone_junto_da_cor(): void
    {
        $this->blade('<x-pill kind="danger">Negado</x-pill>')
            ->assertSee('Negado')
            ->assertSee('bg-danger-soft', false)
            ->assertSee('<svg', false);

        // Tipo desconhecido vira neutro, sem estourar.
        $this->blade('<x-pill kind="qualquer">Algo</x-pill>')->assertSee('bg-subtle', false);
    }

    public function test_status_span_mantem_a_entrada_antiga(): void
    {
        $this->blade('<x-status-span :status="$s" />', ['s' => (object) ['id' => 3, 'portuguese' => 'pendente']])
            ->assertSee('Pendente')
            ->assertSee('bg-warn-soft', false);
    }

    public function test_area_desconhecida_cai_na_cor_padrao(): void
    {
        $this->assertSame(AreaColor::DEFAULT, AreaColor::normalize('nao-existe'));
        $this->assertStringContainsString('--a-portaria', AreaColor::style('portaria'));

        $this->blade('<x-media area="<script>" />')
            ->assertSee('--a-' . AreaColor::DEFAULT, false)
            ->assertDontSee('<script>', false);
    }

    public function test_media_com_foto_tem_substituto_local_e_sem_placehold(): void
    {
        $html = (string) $this->blade('<x-media src="/images/foto.jpg" alt="Foto" initials="MC" area="freela" />');

        $this->assertStringContainsString('src="/images/foto.jpg"', $html);
        $this->assertStringContainsString('onerror="this.remove()"', $html);
        $this->assertStringContainsString('MC', $html);
        $this->assertStringNotContainsString('placehold.co', $html);
    }

    public function test_busca_no_servidor_preserva_filtros_e_volta_para_a_primeira_pagina(): void
    {
        $request = Request::create('/cotacao/mapas', 'GET', ['status' => 'aberto', 'page' => 3, 'q' => 'cloro']);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        $html = (string) $this->blade('<x-search-bar placeholder="Buscar mapa" />');

        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('value="cloro"', $html);
        $this->assertStringContainsString('name="status" value="aberto"', $html);
        $this->assertStringNotContainsString('name="page"', $html);
        $this->assertStringContainsString('Limpar', $html);
    }

    public function test_busca_na_pagina_aponta_o_alvo(): void
    {
        $this->blade('<x-search-bar mode="client" target="#lista" />')
            ->assertSee('laraSearch(', false)
            ->assertSee('#lista', false)
            ->assertDontSee('<form', false);
    }

    public function test_capa_da_area_marca_a_aba_atual_e_o_contador(): void
    {
        $html = (string) $this->blade(
            '<x-area-cover area="info" title="InfoClube" icon="info" :tabs="$tabs" />',
            ['tabs' => [
                ['label' => 'Avisos', 'href' => '/avisos', 'active' => true, 'badge' => 4],
                ['label' => 'Informações', 'href' => '/information'],
            ]],
        );

        $this->assertStringContainsString('InfoClube', $html);
        $this->assertMatchesRegularExpression('#href="/avisos"\s+aria-current="page"#', $html);
        $this->assertStringNotContainsString('href="/information" aria-current', $html);
        $this->assertStringContainsString('>4<', $html);
    }

    public function test_botao_principal_e_grena_e_aceita_tamanho(): void
    {
        $this->blade('<x-primary-button size="sm">Salvar</x-primary-button>')
            ->assertSee('bg-grena', false)
            ->assertSee('h-8', false)
            ->assertSee('type="submit"', false);
    }

    public function test_catalogo_abre_logado_e_sem_bootstrap_grid(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(UserAccess::none());
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $semNotificacoes = Mockery::mock();
        $semNotificacoes->shouldReceive('latest')->andReturnSelf();
        $semNotificacoes->shouldReceive('limit')->andReturnSelf();
        $semNotificacoes->shouldReceive('get')->andReturn(collect());
        $user->shouldReceive('unreadNotifications')->andReturn($semNotificacoes);
        $user->id = 9;
        $user->name = 'Pessoa de Teste';

        $html = $this->actingAs($user)->get(route('design.components'))->assertOk()->getContent();

        $this->assertStringContainsString('Catálogo de componentes', $html);
        $this->assertStringContainsString('window.laraTheme', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
    }
}
