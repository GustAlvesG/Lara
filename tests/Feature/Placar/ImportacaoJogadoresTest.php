<?php

namespace Tests\Feature\Placar;

use App\Imports\JogadorImport;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Database\Seeders\ModalidadeSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * Importação em massa de jogadores por planilha .xlsx.
 *
 * A importação é tudo-ou-nada (ver SpreadsheetImport): havendo erro em
 * qualquer linha, nada é gravado — assim ninguém importa metade do elenco
 * e reenvia o arquivo duplicando o resto.
 */
class ImportacaoJogadoresTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Equipe $equipe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        (new ModalidadeSeeder())->run();

        $this->equipe = Equipe::create(['nome' => 'Clube dos Funcionários', 'nome_curto' => 'CF', 'ativo' => true]);
    }

    /** Gera um .xlsx temporário com o cabeçalho do modelo e as linhas dadas. */
    private function planilha(array $linhas, ?array $cabecalho = null): string
    {
        $colunas = array_values((new JogadorImport())->columns());
        $cabecalho ??= $colunas;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($cabecalho as $i => $titulo) {
            $sheet->setCellValue([$i + 1, 1], $titulo);
        }

        foreach ($linhas as $l => $linha) {
            foreach ($linha as $c => $valor) {
                $sheet->setCellValueExplicit(
                    [$c + 1, $l + 2],
                    (string) $valor,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
        }

        $caminho = tempnam(sys_get_temp_dir(), 'jogadores_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);
        $spreadsheet->disconnectWorksheets();

        return $caminho;
    }

    /** Ordem das colunas do modelo: nome, equipe, modalidade, exibicao, nasc, categoria, numero, posicao, temporada */
    private function linha(array $valores): array
    {
        return array_replace(array_fill(0, 9, ''), $valores);
    }

    public function test_importa_cadastro_e_ja_vincula_ao_elenco(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Carlos Souza', 1 => 'Clube dos Funcionários', 2 => 'futsal',
                3 => 'Carlinhos', 4 => '15/03/2004', 5 => 'Adulto', 6 => '10', 7 => 'Ala', 8 => '2026']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame([], $resultado['errors']);
        $this->assertSame(1, $resultado['imported']);

        $jogador = Jogador::first();
        $this->assertSame('Carlos Souza', $jogador->nome);
        $this->assertSame('Carlinhos', $jogador->nome_exibicao);
        $this->assertSame($this->equipe->id, $jogador->equipe_id);
        $this->assertSame(Modalidade::where('slug', 'futsal')->first()->id, $jogador->modalidade_id);
        $this->assertSame('2004-03-15', $jogador->data_nascimento->toDateString());

        // O time foi criado junto e o jogador entrou no elenco.
        $time = Time::first();
        $this->assertSame('Adulto', $time->categoria);
        $this->assertDatabaseHas('elencos', [
            'time_id' => $time->id, 'jogador_id' => $jogador->id,
            'numero' => '10', 'posicao' => 'Ala', 'temporada' => 2026,
        ]);
    }

    public function test_sem_as_colunas_de_vinculo_apenas_cadastra(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Só Cadastro', 1 => 'Clube dos Funcionários', 2 => 'futsal']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(1, $resultado['imported']);
        $this->assertSame(0, Elenco::count());
        $this->assertSame(0, Time::count());
    }

    public function test_varios_jogadores_do_mesmo_time_caem_no_mesmo_elenco(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Jogador Um', 1 => 'Clube dos Funcionários', 2 => 'futsal', 5 => 'Adulto', 6 => '1']),
            $this->linha([0 => 'Jogador Dois', 1 => 'Clube dos Funcionários', 2 => 'futsal', 5 => 'Adulto', 6 => '2']),
            $this->linha([0 => 'Jogador Três', 1 => 'Clube dos Funcionários', 2 => 'futsal', 5 => 'Adulto', 6 => '3']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(3, $resultado['imported']);
        // Um time só, com os três dentro.
        $this->assertSame(1, Time::count());
        $this->assertSame(3, Elenco::count());
    }

    /** "Sub 15" na planilha não pode criar um time separado do "Sub-15". */
    public function test_categoria_com_grafia_diferente_cai_no_time_existente(): void
    {
        $futsal = Modalidade::where('slug', 'futsal')->first();
        $existente = Time::create([
            'equipe_id' => $this->equipe->id, 'modalidade_id' => $futsal->id,
            'categoria' => 'Sub-15', 'ativo' => true,
        ]);

        $arquivo = $this->planilha([
            $this->linha([0 => 'Da Base', 1 => 'Clube dos Funcionários', 2 => 'futsal', 5 => 'Sub 15']),
        ]);

        (new JogadorImport())->import($arquivo);

        $this->assertSame(1, Time::count());
        $this->assertDatabaseHas('elencos', ['time_id' => $existente->id]);
    }

    public function test_equipe_pode_ser_informada_pelo_nome_curto(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Pelo Apelido', 1 => 'CF', 2 => 'futsal']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(1, $resultado['imported']);
        $this->assertSame($this->equipe->id, Jogador::first()->equipe_id);
    }

    public function test_equipe_inexistente_para_a_importacao_inteira(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Válido', 1 => 'Clube dos Funcionários', 2 => 'futsal']),
            $this->linha([0 => 'Inválido', 1 => 'Equipe Que Não Existe', 2 => 'futsal']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(0, $resultado['imported']);
        $this->assertCount(1, $resultado['errors']);
        $this->assertStringContainsString('Equipe Que Não Existe', $resultado['errors'][0]);
        // Tudo-ou-nada: nem o válido entrou.
        $this->assertSame(0, Jogador::count());
    }

    public function test_modalidade_desconhecida_e_recusada(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Handebol', 1 => 'Clube dos Funcionários', 2 => 'handebol']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(0, $resultado['imported']);
        $this->assertStringContainsString('handebol', $resultado['errors'][0]);
    }

    /**
     * O modelo pede só nome e data de nascimento como dado pessoal —
     * documento saiu do cadastro e não pode voltar por planilha.
     */
    public function test_o_modelo_nao_tem_mais_a_coluna_documento(): void
    {
        $colunas = (new JogadorImport())->columns();

        $this->assertArrayNotHasKey('documento', $colunas);
        $this->assertNotContains('Documento', array_values($colunas));
    }

    /** Número sem categoria seria descartado em silêncio — melhor avisar. */
    public function test_numero_sem_categoria_do_time_e_recusado(): void
    {
        $arquivo = $this->planilha([
            $this->linha([0 => 'Sem Time', 1 => 'Clube dos Funcionários', 2 => 'futsal', 6 => '10']),
        ]);

        $resultado = (new JogadorImport())->import($arquivo);

        $this->assertSame(0, $resultado['imported']);
        $this->assertStringContainsString('categoria', $resultado['errors'][0]);
    }

    public function test_planilha_sem_as_colunas_do_modelo_e_recusada_inteira(): void
    {
        $arquivo = $this->planilha(
            [[0 => 'Fulano']],
            ['Nome *', 'Telefone'] // cabeçalho errado, sem equipe/modalidade
        );

        $this->expectException(\App\Exceptions\SpreadsheetImportException::class);

        (new JogadorImport())->import($arquivo);
    }

    public function test_modelo_para_download_tem_o_cabecalho_esperado(): void
    {
        $resposta = $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.jogadores.import.template'))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $resposta->headers->get('content-type'),
        );
        $this->assertStringContainsString('modelo-importacao-jogadores.xlsx', $resposta->headers->get('content-disposition'));
    }

    public function test_a_tela_de_cadastro_oferece_a_importacao(): void
    {
        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.jogadores.create'))
            ->assertOk()
            ->assertSee('Importar por planilha')
            ->assertSee('Baixar modelo');
    }
}
