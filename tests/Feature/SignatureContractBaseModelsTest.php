<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\SignatureContractClause;
use App\Models\SignatureContractModel;
use App\Services\Signature\Contracts\ContractBaseModels;
use App\Services\Signature\Contracts\ContractContent;
use App\Services\Signature\Contracts\ContractRenderer;
use App\Services\Signature\Contracts\ContractService;
use App\Services\Signature\Directors\DirectorAreaService;
use Tests\Concerns\CreatesContractSchema;
use Tests\TestCase;

/**
 * Modelos-base de contrato (04/11/2026), tirados dos PDFs do clube: cada um,
 * preenchido, tem de passar pela mesma régua do envio à Gerência — sem
 * variável inexistente, remissão quebrada, remissão digitada nem termo de
 * parte parecido com outro.
 */
class SignatureContractBaseModelsTest extends TestCase
{
    use CreatesContractSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContractSchema();
        $this->criaPresidente();

        $diretores = \Mockery::mock(DirectorAreaService::class);
        $diretores->shouldReceive('options')->andReturn([]);
        $this->app->instance(DirectorAreaService::class, $diretores);
    }

    public function test_cada_modelo_preenchido_nao_tem_problema(): void
    {
        $renderer = app(ContractRenderer::class);
        $nomes = [];

        foreach (ContractBaseModels::models() as $modelo) {
            $nomes[] = $modelo['name'];
            $conteudo = $this->preenche(app(ContractService::class)->modelContent(new SignatureContractModel(['content' => $modelo['content']])));

            $problemas = collect($renderer->problems($conteudo))
                // Sem e-mail de quem assina é só aviso de assinatura a distância.
                ->reject(fn(array $p) => str_contains($p['message'], 'sem e-mail'))
                ->pluck('message')
                ->all();

            $this->assertSame([], $problemas, $modelo['name']);

            // Toda variável declarada aparece no texto (senão é campo sobrando no formulário).
            $texto = json_encode($modelo['content']['clauses'], JSON_UNESCAPED_UNICODE) . json_encode($modelo['content']['considerations'], JSON_UNESCAPED_UNICODE);

            foreach ($modelo['content']['variables'] as $variavel) {
                $this->assertStringContainsString('[[' . $variavel['key'] . ']]', $texto, $modelo['name'] . ': ' . $variavel['key']);
            }
        }

        $this->assertCount(11, array_unique($nomes));
    }

    public function test_clausulas_avulsas_tem_itens(): void
    {
        foreach (ContractBaseModels::clauses() as $clausula) {
            $this->assertNotSame([], $clausula['items'], $clausula['title']);
            $this->assertNotSame('', $clausula['items'][0]['html']);
        }
    }

    public function test_comando_grava_no_setor_e_nao_duplica(): void
    {
        $comercial = Sector::create(['name' => 'Comercial']);
        Sector::create(['name' => 'RH']);

        $this->artisan('contratos:modelos-base', ['--setor' => ['comercial']])->assertSuccessful();
        $this->artisan('contratos:modelos-base', ['--setor' => [(string) $comercial->id]])->assertSuccessful();

        $this->assertSame(11, SignatureContractModel::where('sector_id', $comercial->id)->count());
        $this->assertSame(count(ContractBaseModels::clauses()), SignatureContractClause::where('sector_id', $comercial->id)->count());
        $this->assertSame(0, SignatureContractModel::where('sector_id', '!=', $comercial->id)->count());
        $this->assertSame(ContractBaseModels::AUTHOR, SignatureContractModel::first()->created_by_name);

        $this->artisan('contratos:modelos-base')->assertFailed();
        $this->artisan('contratos:modelos-base', ['--setor' => ['Inexistente']])->assertFailed();
    }

    /** O que quem redige preencheria: partes, variáveis e assinaturas. */
    private function preenche(array $conteudo): array
    {
        $valores = [
            'money' => '1.500,00',
            'number' => '5',
            'percent' => '10',
            'date' => '05/10/2026',
            'date_long' => '05/10/2026',
            'cpf' => '529.982.247-25',
            'cnpj' => '04.758.575/0001-29',
            'text' => 'texto de exemplo',
        ];

        $conteudo['variables'] = array_map(fn(array $v) => ['value' => $valores[$v['type']]] + $v, $conteudo['variables']);

        $conteudo['parties'] = array_map(fn(array $p) => array_merge($p, [
            'name' => 'Parte de Exemplo',
            'document' => $p['kind'] === 'pf' ? '529.982.247-25' : '04.758.575/0001-29',
            'address' => 'Rua 33, nº 145, Volta Redonda/RJ',
            'rep_name' => 'Representante de Exemplo',
            'rep_cpf' => '111.444.777-35',
        ]), $conteudo['parties']);

        $conteudo['signers'] = array_map(fn(array $s) => array_merge($s, [
            'name' => 'Pessoa de Exemplo',
            'cpf' => '111.444.777-35',
        ]), $conteudo['signers']);

        return ContractContent::normalize($conteudo);
    }
}
