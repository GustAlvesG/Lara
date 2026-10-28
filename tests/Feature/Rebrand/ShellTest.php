<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\User;
use App\View\Navigation;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * A casca (navegação por Módulos, a única desde 28/10/2026): o menu calculado
 * uma vez e a página atual dentro dele (capa, abas, "você está em", recentes).
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

    public function test_fora_de_qualquer_modulo_nao_ha_pagina_atual(): void
    {
        $nav = Navigation::build($this->portaria(), $this->requisicaoEm('design.components'));

        $this->assertNull($nav['current']);
    }

    public function test_criar_e_editar_caem_na_aba_do_recurso(): void
    {
        $placar = $this->usuario(new UserAccess([P::PLACAR_CADASTRO]));

        $current = Navigation::build($placar, $this->requisicaoEm('placar.times.create'))['current'];
        $this->assertSame('placar.times.index', $current['key']);
        $this->assertSame('Placar Clube', $current['group']['label']);
        $this->assertSame('Times', collect($current['tabs'])->firstWhere('active', true)['label']);

        // Rota com parâmetro e nome que não repete o da aba (escalacao).
        $current = Navigation::build($placar, $this->requisicaoEm('placar.jogos.escalacao.edit', [7]))['current'];
        $this->assertSame('placar.jogos.index', $current['key']);
    }

    public function test_vale_o_endereco_mais_longo_e_nao_o_primeiro_do_grupo(): void
    {
        // /company/one-off-accesses/create começa com /company (Empresas) e
        // com /company/one-off-accesses (Liberação Pontual): vence o segundo.
        $externos = $this->usuario(new UserAccess([P::EXTERNOS_LIBERACAO_PONTUAL]));
        $nav = Navigation::build($externos, $this->requisicaoEm('company.one-off.create'));
        $this->assertSame('company.one-off.index', $nav['current']['key']);

        // Lotes têm outro prefixo de nome, mas moram em /freelancer-services.
        $freela = $this->usuario(new UserAccess([P::FREELANCERS_SERVICOS_LISTAR, P::FREELANCERS_FINANCEIRO]));
        $nav = Navigation::build($freela, $this->requisicaoEm('freelancer-batches.index'));
        $this->assertSame('freelancer-services.index', $nav['current']['key']);

        $nav = Navigation::build($freela, $this->requisicaoEm('freelancer-services.finance.orphans'));
        $this->assertSame('freelancer-services.finance', $nav['current']['key']);
    }

    public function test_item_solto_do_menu_tem_capa_sem_abas(): void
    {
        $this->actingAs($this->usuario(new UserAccess([P::HOME_ASSISTANT])));
        $this->requisicaoEm('home-assistant.index');

        $html = (string) $this->blade(<<<'BLADE'
            <x-app-layout :bootstrap-grid="false">
                conteudo
            </x-app-layout>
            BLADE);

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Home Assistant\s*</h1>#', $html);
        $this->assertStringContainsString('toggleFav(currentKey)', $html);
        $this->assertStringNotContainsString('aria-label="Páginas de', $html);
    }

    public function test_telas_da_conta_tem_capa_com_os_atalhos_da_conta(): void
    {
        $admin = $this->usuario(new UserAccess([P::USUARIOS_GERENCIAR]));

        $current = Navigation::build($admin, $this->requisicaoEm('users.create'))['current'];
        $this->assertSame('users.index', $current['key']);
        $this->assertSame('Conta', $current['group']['label']);
        // Setores e Meu setor ficam de fora: sem permissão, sem aba.
        $this->assertSame(['Perfil', 'Documentação', 'Usuários'], array_column($current['tabs'], 'label'));
    }

    public function test_layout_traz_so_a_navegacao_por_modulos(): void
    {
        $html = $this->actingAs($this->portaria())->get(route('docs.show', 'README'))->assertOk()->getContent();

        // Os menus Lateral e Superior saíram em 28/10/2026: nada de modo.
        $this->assertStringNotContainsString('laraNavMode', $html);
        $this->assertStringNotContainsString('nav-only-', $html);
        $this->assertStringNotContainsString('aria-label="Modo de navegação"', $html);
        // Um sino só, o da barra.
        $this->assertSame(1, substr_count($html, 'aria-label="Notificações'));

        // Barra: painel de Módulos, atalhos, conta com iniciais.
        $this->assertStringContainsString('id="lara-launcher"', $html);
        $this->assertStringContainsString('aria-label="Favoritos e recentes"', $html);
        $this->assertMatchesRegularExpression('#aria-label="Sua conta"[^>]*>\s*PS\s*</button>#', $html);

        // Painel: só as áreas que sobram para a pessoa.
        $this->assertStringContainsString(route('parking.search'), $html);
        $this->assertStringNotContainsString(route('freelancers.index'), $html);

        // Conta: tema.
        foreach (['Claro', 'Escuro', 'Sistema'] as $opcao) {
            $this->assertStringContainsString($opcao, $html);
        }
        $this->assertStringContainsString("window.laraTheme.set('system')", $html);

        // Um documento cai na aba Documentação, na capa da Conta.
        $this->assertStringContainsString('aria-label="Páginas de Conta"', $html);
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
