<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Freelancer;
use App\Models\FunctionFreelancer;
use App\Models\Placar\Competicao;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogo;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Freelancers e Placar repaginados: casco novo (sem título duplicado), ações
 * em grená, busca em toda lista e cartões onde há foto ou escudo.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class FreelancersPlacarScreensTest extends TestCase
{
    use RendersScreens;

    private function equipe()
    {
        return $this->usuario(new UserAccess([
            P::FREELANCERS_CADASTRO, P::FREELANCERS_FUNCOES, P::FREELANCERS_SERVICOS_LISTAR, P::PLACAR_CADASTRO, P::PLACAR_SCOUT,
        ]));
    }

    private function miolo(string $html): string
    {
        $inicio = strpos($html, '<main>');
        $fim = strpos($html, '<footer');

        return substr($html, (int) $inicio, $fim === false ? null : $fim - (int) $inicio);
    }

    public function test_freelancers_em_cartoes_com_busca_na_pagina(): void
    {
        $freelancer = (new Freelancer)->forceFill([
            'id' => 5, 'name' => 'Carla Garçom', 'cpf' => '123.456.789-09', 'pix_key' => 'carla@pix.com', 'image' => null,
        ]);
        $freelancer->exceeds_weekly_limit = true;
        $freelancer->function_counts = ['Garçom' => 3];
        $freelancer->freelancer_services_count = 3;

        $html = $this->tela($this->equipe(), 'freelancers.index', [], 'freelancer.freelancers.index', [
            'freelancers' => collect([$freelancer]), 'search' => null,
        ]);

        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('id="freelancers"', $html);
        // CPF também só com os dígitos, que é como se costuma digitar.
        $this->assertStringContainsString('12345678909', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true">\s*CG\s*<#', $html);
        $this->assertStringContainsString('Excesso de serviços', $html);
        $this->assertStringContainsString('Garçom · 3', $html);
        $this->assertStringContainsString(route('freelancers.destroy', 5), $html);
    }

    public function test_funcoes_com_busca_e_sem_titulo_duplicado(): void
    {
        $function = (new FunctionFreelancer)->forceFill(['id' => 2, 'name' => 'Garçom', 'description' => 'Salão', 'price' => 12.5]);
        $function->freelancer_services_count = 8;

        $html = $this->tela($this->equipe(), 'freelancer-functions.index', [], 'freelancer.functions.index', [
            'functions' => collect([$function]),
        ]);

        $this->assertStringContainsString('id="funcoes"', $html);
        $this->assertStringContainsString('R$ 12,50', $html);
        // Título uma vez só: o do x-page-title (sem o slot header de antes).
        $this->assertSame(1, substr_count($this->miolo($html), '>Funções</h2>'));
        $this->assertStringNotContainsString('<h1', $this->miolo($html));
    }

    public function test_casco_antigo_sem_titulo_duplicado_nas_telas_de_freelancer_e_placar(): void
    {
        $views = glob(resource_path('views/{freelancer,placar}/**/*.blade.php'), GLOB_BRACE);
        $this->assertNotEmpty($views);

        foreach ($views as $view) {
            $source = file_get_contents($view);
            if (str_contains($source, '<h1')) {
                $this->assertStringNotContainsString('<x-slot name="header">', $source, $view);
            }
            $this->assertStringNotContainsString('bg-subtle min-h-screen', $source, $view);
        }
    }

    public function test_selecionar_todos_respeita_a_busca_no_financeiro_e_nos_lotes(): void
    {
        $financeiro = file_get_contents(resource_path('views/freelancer/services/partials/finance-table.blade.php'));
        $this->assertStringContainsString('visiblePending()', $financeiro);
        // Busca fora do formulário de baixa: Enter nela não envia pagamento.
        $this->assertLessThan(strpos($financeiro, '<form method="POST"'), strpos($financeiro, '<x-search-bar'));

        $lotes = file_get_contents(resource_path('views/freelancer/batches/index.blade.php'));
        $this->assertStringContainsString("style.display !== 'none'", $lotes);
    }

    public function test_equipes_em_cartoes_com_escudo_ou_iniciais(): void
    {
        $equipe = (new Equipe)->forceFill(['id' => 3, 'nome' => 'Unidos da Vila', 'cidade' => 'Rio', 'ativo' => true, 'criado_em_campo' => true, 'logo_path' => null]);
        $equipe->times_count = 2;

        $html = $this->tela($this->equipe(), 'placar.equipes.index', [], 'placar.equipes.index', [
            'equipes' => new LengthAwarePaginator([$equipe], 1, 20, 1, ['path' => route('placar.equipes.index')]),
        ], ['busca' => 'vila']);

        $this->assertStringContainsString('name="busca"', $html);
        $this->assertStringContainsString('value="vila"', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true">\s*UD\s*</span>#', $html);
        $this->assertStringContainsString('Criada em campo', $html);
        $this->assertStringContainsString(route('placar.equipes.show', 3), $html);
        // Ação principal em grená, não no verde de antes.
        $this->assertStringNotContainsString('hover:bg-ok ', $this->miolo($html));
    }

    public function test_competicoes_e_jogos_ganharam_busca(): void
    {
        $html = $this->tela($this->equipe(), 'placar.competicoes.index', [], 'placar.competicoes.index', [
            'competicoes' => new LengthAwarePaginator([], 0, 20, 1, ['path' => route('placar.competicoes.index')]),
        ]);
        $this->assertStringContainsString('name="busca"', $html);

        $sql = Jogo::query()->busca('vila')->toSql();
        foreach (['"local"', '"times"', '"equipes"', '"competicoes"'] as $alvo) {
            $this->assertStringContainsString($alvo, $sql);
        }
        $this->assertStringNotContainsString('like', Jogo::query()->busca(' ')->toSql());

        $this->assertStringContainsString('temporada', Competicao::query()->busca('2026')->toSql());
    }
}
