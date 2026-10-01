<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\User;
use App\View\Navigation;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * A casca nova (navegação por Módulos): o menu calculado uma vez, a página atual
 * dentro dele (capa, abas, "você está em", recentes) e os menus de antes
 * continuando disponíveis como opção.
 *
 * Usuário mock (o User está preso à conexão mysql), como em
 * NavigationMenuTest.
 */
class ShellTest extends TestCase
{
    use RendersScreens;

    private function portaria(): User
    {
        return $this->usuario(new UserAccess([P::SIV_BUSCA, P::SIV_FROTA, P::SIV_VEICULOS]));
    }

    public function test_pagina_atual_traz_a_area_e_as_abas_que_a_pessoa_pode_abrir(): void
    {
        $nav = Navigation::build($this->portaria(), $this->requisicaoEm('fleet.vehicles'));

        $current = $nav['current'];
        $this->assertSame('fleet.vehicles', $current['key']);
        $this->assertSame('SIV', $current['group']['label']);
        $this->assertSame('portaria', $current['group']['area']);

        // Viagens e Placas Diretoria ficam de fora: sem permissão, sem aba.
        $this->assertSame(['Busca', 'Frota', 'Veículos'], array_column($current['tabs'], 'label'));
        $this->assertSame([false, false, true], array_column($current['tabs'], 'active'));
    }

    public function test_indice_leva_a_cor_da_area_para_os_atalhos(): void
    {
        $nav = Navigation::build($this->portaria(), $this->requisicaoEm('docs.index'));

        $frota = collect($nav['index'])->firstWhere('key', 'fleet.index');
        $this->assertSame('SIV', $frota['group']);
        $this->assertStringContainsString('--a-portaria', $frota['areaStyle']);

        // Atalhos da conta também entram na busca.
        $this->assertSame('Conta', collect($nav['index'])->firstWhere('key', 'docs.index')['group']);

        $siv = collect($nav['groups'])->firstWhere('key', 'siv');
        $this->assertSame(['car', 3, route('parking.search')], [$siv['glyph'], $siv['pages'], $siv['url']]);
    }

    public function test_fora_do_menu_nao_ha_pagina_atual(): void
    {
        $nav = Navigation::build($this->portaria(), $this->requisicaoEm('docs.show', ['README']));

        $this->assertNull($nav['current']);
    }

    public function test_layout_traz_as_areas_e_os_menus_de_antes_como_opcao(): void
    {
        $html = $this->actingAs($this->portaria())->get(route('docs.show', 'README'))->assertOk()->getContent();

        // Modo gravado no <html> antes da pintura, Módulos por padrão.
        $this->assertStringContainsString("localStorage.getItem('laraNavMode')", $html);
        $this->assertStringContainsString('class="nav-only-areas contents"', $html);
        $this->assertStringContainsString('class="nav-only-side"', $html);
        $this->assertStringContainsString('class="nav-only-top contents"', $html);

        // Barra: painel de Módulos, atalhos, conta com iniciais.
        $this->assertStringContainsString('id="lara-launcher"', $html);
        $this->assertStringContainsString('aria-label="Favoritos e recentes"', $html);
        $this->assertMatchesRegularExpression('#aria-label="Sua conta"[^>]*>\s*PS\s*</button>#', $html);

        // Painel: só as áreas que sobram para a pessoa.
        $this->assertStringContainsString(route('parking.search'), $html);
        $this->assertStringNotContainsString(route('freelancers.index'), $html);

        // Conta: tema e modo de navegação com as três opções.
        foreach (['Claro', 'Escuro', 'Sistema', 'Módulos', 'Lateral', 'Superior'] as $opcao) {
            $this->assertStringContainsString($opcao, $html);
        }
        $this->assertStringContainsString("window.laraTheme.set('system')", $html);

        // Documentação está fora do menu: sem capa de área.
        $this->assertStringNotContainsString('aria-label="Páginas de', $html);
    }

    public function test_capa_da_area_entra_sozinha_com_as_abas_e_a_estrela(): void
    {
        $this->actingAs($this->portaria());
        $this->requisicaoEm('fleet.vehicles');

        // A quebra de linha antes do <x-slot> importa: colado na tag do
        // componente, o compilador do Blade deixa um buffer de saída aberto.
        $html = (string) $this->blade(<<<'BLADE'
            <x-app-layout :bootstrap-grid="false">
                <x-slot:cover-actions><a href="/novo">Novo veículo</a></x-slot:cover-actions>
                conteudo
            </x-app-layout>
            BLADE);

        $this->assertStringContainsString('aria-label="Páginas de SIV"', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('fleet.vehicles'), '#') . '"\s+aria-current="page"#', $html);
        $this->assertStringContainsString('toggleFav(currentKey)', $html);
        $this->assertStringContainsString('Novo veículo', $html);
        // A página entra nos recentes pelo laraShell.
        $this->assertStringContainsString(", 'fleet.vehicles', JSON.parse(", $html);
        // "Você está em".
        $this->assertMatchesRegularExpression('#SIV</a>\s*<span aria-hidden="true">/</span>\s*<b[^>]*>Veículos</b>#', $html);
    }

    public function test_tela_pode_dispensar_a_capa(): void
    {
        $this->actingAs($this->portaria());
        $this->requisicaoEm('fleet.vehicles');

        $html = (string) $this->blade('<x-app-layout :cover="false">conteudo</x-app-layout>');

        $this->assertStringNotContainsString('aria-label="Páginas de SIV"', $html);
        $this->assertStringContainsString('conteudo', $html);
    }
}
