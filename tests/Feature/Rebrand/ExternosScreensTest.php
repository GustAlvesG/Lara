<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Company\Company;
use App\Models\Company\CompanyAccessLog;
use App\Models\Company\OneOffAccess;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Externos repaginado: paleta nova em todas as telas (via tradução das
 * classes antigas), substituto local no lugar do placehold.co e busca em toda
 * lista — no servidor para os históricos paginados, na página para as listas
 * que chegam inteiras (liberações do dia, fila do Uber).
 *
 * Models não salvos, sem banco; usuário mock.
 */
class ExternosScreensTest extends TestCase
{
    use RendersScreens;

    private function portaria()
    {
        return $this->usuario(new UserAccess([P::EXTERNOS_HISTORICO, P::EXTERNOS_LIBERACAO_PONTUAL, P::EXTERNOS_CARROS_APLICATIVO]));
    }

    private function empresa(int $id, array $attributes = []): Company
    {
        $company = (new Company)->forceFill(array_merge(['id' => $id, 'name' => 'Acme Serviços', 'image' => null], $attributes));
        $company->setRelation('workers', collect());
        $company->setRelation('rules', collect());

        return $company;
    }

    /** O miolo da tela, sem o layout (menus de antes ainda têm paleta antiga). */
    private function miolo(string $html): string
    {
        $inicio = strpos($html, '<main>');
        $fim = strpos($html, '<footer');

        return substr($html, (int) $inicio, $fim === false ? null : $fim - (int) $inicio);
    }

    public function test_empresas_sem_placehold_e_com_busca(): void
    {
        $html = $this->tela($this->portaria(), 'company.index', [], 'companies.index', [
            'companies' => collect([$this->empresa(1), $this->empresa(2, ['name' => 'Elevadores Rio', 'image' => 'empresas/rio.png'])]),
            'accessStatuses' => [1 => true, 2 => false],
        ]);

        $this->assertStringNotContainsString('placehold.co', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true">\s*AS\s*</span>#', $html);
        $this->assertStringContainsString('src="' . asset('images/empresas/rio.png') . '"', $html);
        $this->assertStringContainsString('id="search-filter-text"', $html);
        $this->assertStringContainsString('aria-label="Páginas de Externos"', $html);
        $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo)-\d{2,3}\b#', $this->miolo($html));
    }

    public function test_historico_de_acessos_tem_busca_livre_junto_dos_filtros(): void
    {
        $log = (new CompanyAccessLog)->forceFill([
            'id' => 1, 'company_id' => 1, 'target' => '12345678909', 'allowed' => true, 'reason' => 'regra vigente',
            'created_at' => Carbon::now(),
        ]);
        $log->setRelation('company', $this->empresa(1));
        foreach (['worker', 'appDriver', 'uberRequest', 'freelancer', 'freelancerService', 'oneOffAccess'] as $relation) {
            $log->setRelation($relation, null);
        }

        $html = $this->tela($this->portaria(), 'company.access.logs', [], 'companies.access-logs', [
            'logs' => new LengthAwarePaginator([$log], 1, 25, 1, ['path' => route('company.access.logs')]),
            'companies' => collect([$this->empresa(1)]),
            'stats' => ['total' => 1, 'allowed' => 1, 'denied' => 0],
            'type' => 'all',
        ], ['q' => '123456']);

        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('value="123456"', $html);
        $this->assertStringContainsString('12345678909', $html);
        $this->assertStringContainsString('aria-label="Páginas de Externos"', $html);
    }

    public function test_busca_do_historico_procura_em_placa_pessoa_empresa_e_pedido(): void
    {
        $sql = CompanyAccessLog::query()->search('rkt4f21')->toSql();

        foreach (['target', 'obs', 'reason', 'company_workers', 'companies', 'uber_access_requests'] as $alvo) {
            $this->assertStringContainsString($alvo, $sql);
        }

        // Termo vazio não filtra.
        $this->assertStringNotContainsString('like', CompanyAccessLog::query()->search('  ')->toSql());
    }

    public function test_liberacoes_do_dia_filtram_na_pagina(): void
    {
        $liberacao = (new OneOffAccess)->forceFill([
            'id' => 4, 'name' => 'Técnico do Elevador', 'cpf' => '12345678909', 'reason' => 'Pane no elevador social',
            'access_date' => Carbon::today(), 'created_at' => Carbon::now(),
        ]);
        $liberacao->setRelation('creator', null);

        $html = $this->tela($this->portaria(), 'company.one-off.index', [], 'companies.one-off.index', [
            'accesses' => collect([$liberacao]),
            'stats' => ['total' => 1, 'available' => 1, 'used' => 0],
            'date' => Carbon::today(),
        ]);

        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('id="liberacoes"', $html);
        $this->assertStringContainsString('123.456.789-09', $html);
        $this->assertStringContainsString(route('company.one-off.cancel', 4), $html);
    }

    public function test_fila_do_uber_nao_recarrega_no_meio_de_uma_busca(): void
    {
        $html = $this->tela($this->portaria(), 'company.uber.waiting', [], 'companies.uber.waiting', [
            'validos' => collect(), 'expirados' => collect(), 'emPreenchimento' => collect(),
        ]);

        $this->assertStringContainsString('Nenhum pedido aguardando motorista no momento.', $html);
        $this->assertStringContainsString("document.getElementById('busca-fila')", $html);
        $this->assertStringContainsString('!(busca && busca.value.trim())', $html);
        // Sem pedido, não há o que buscar.
        $this->assertStringNotContainsString('id="busca-fila"', $html);
    }
}
