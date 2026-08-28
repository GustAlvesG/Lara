<?php

namespace Tests\Feature\Placar\Web;

use App\Models\Placar\Equipe;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\Concerns\MocksPlacarUser;
use Tests\TestCase;

/**
 * A tela de novo jogo lista TODOS os times ativos num select só (o filtro
 * por modalidade é do Alpine, na tela). Com o cadastro crescendo, achar o
 * time na lista é o trabalho — e por isso a ordem e o rótulo importam:
 *
 * - **alfabética pelo nome exibido**, que é o que o olho procura. Ordenar
 *   por categoria, como era antes, espalhava os times do mesmo clube por
 *   pontas opostas da lista;
 * - **modalidade no rótulo**, para o time certo se distinguir antes de a
 *   modalidade ser escolhida (é quando a lista está inteira à mostra).
 */
class NovoJogoTest extends TestCase
{
    use MigratesPlacarSchema;
    use MocksPlacarUser;

    private Modalidade $volei;
    private Modalidade $futsal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $this->volei = Modalidade::create(['nome' => 'Vôlei', 'slug' => 'volei', 'ativo' => true]);
        $this->futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
    }

    /** O nome exibido do time sai de `nome_curto` + categoria (ver Time). */
    private function time(string $equipe, string $curto, string $categoria, Modalidade $modalidade): Time
    {
        $equipe = Equipe::firstOrCreate(
            ['nome' => $equipe],
            ['nome_curto' => $curto, 'ativo' => true],
        );

        return Time::create([
            'equipe_id' => $equipe->id, 'modalidade_id' => $modalidade->id,
            'categoria' => $categoria, 'ativo' => true,
        ]);
    }

    private function tela(): string
    {
        return $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.jogos.create'))
            ->assertOk()
            ->getContent();
    }

    /** Onde cada nome aparece no HTML — é assim que se afere a ordem. */
    private function posicao(string $html, string $trecho): int
    {
        $onde = strpos($html, $trecho);
        $this->assertNotFalse($onde, "não encontrei '{$trecho}' na tela");

        return $onde;
    }

    public function test_times_saem_em_ordem_alfabetica(): void
    {
        $this->time('Zebra Futebol Clube', 'Zebra', 'Adulto', $this->futsal);
        $this->time('Atibaia Esporte Clube', 'Atibaia', 'Adulto', $this->futsal);
        $this->time('Marília Vôlei', 'Marília', 'Adulto', $this->volei);

        $html = $this->tela();

        $this->assertLessThan($this->posicao($html, 'Marília Adulto'), $this->posicao($html, 'Atibaia Adulto'));
        $this->assertLessThan($this->posicao($html, 'Zebra Adulto'), $this->posicao($html, 'Marília Adulto'));
    }

    /**
     * Comparado byte a byte, "Á" (0xC3 0x81) vem depois de "Z" e o time
     * acentuado ia parar no fim da lista, longe de onde se procura por ele.
     */
    public function test_acento_nao_joga_o_time_para_o_fim_da_lista(): void
    {
        $this->time('Atibaia Esporte Clube', 'Atibaia', 'Adulto', $this->futsal);
        $this->time('Ática Clube', 'Ática', 'Adulto', $this->futsal);
        $this->time('Atlético Operário', 'Atlético', 'Adulto', $this->futsal);
        $this->time('Zebra Futebol Clube', 'Zebra', 'Adulto', $this->futsal);

        $html = $this->tela();

        $this->assertLessThan($this->posicao($html, 'Ática Adulto'), $this->posicao($html, 'Atibaia Adulto'));
        $this->assertLessThan($this->posicao($html, 'Atlético Adulto'), $this->posicao($html, 'Ática Adulto'));
        $this->assertLessThan($this->posicao($html, 'Zebra Adulto'), $this->posicao($html, 'Atlético Adulto'));
    }

    /** A ordem é do nome, não da categoria — era esse o critério antigo. */
    public function test_ordem_alfabetica_ignora_a_categoria(): void
    {
        $this->time('Zebra Futebol Clube', 'Zebra', 'Adulto', $this->futsal);
        $this->time('Atibaia Esporte Clube', 'Atibaia', 'Sub-15', $this->futsal);

        $html = $this->tela();

        $this->assertLessThan($this->posicao($html, 'Zebra Adulto'), $this->posicao($html, 'Atibaia Sub-15'));
    }

    /** Times do mesmo clube ficam juntos, um por categoria. */
    public function test_times_do_mesmo_clube_ficam_lado_a_lado(): void
    {
        $this->time('Clube dos Funcionários', 'CF', 'Adulto', $this->futsal);
        $this->time('Marília Vôlei', 'Marília', 'Adulto', $this->volei);
        $this->time('Clube dos Funcionários', 'CF', 'Sub-15', $this->futsal);

        $html = $this->tela();

        $this->assertLessThan($this->posicao($html, 'CF Sub-15'), $this->posicao($html, 'CF Adulto'));
        $this->assertLessThan($this->posicao($html, 'Marília Adulto'), $this->posicao($html, 'CF Sub-15'));
    }

    /**
     * Antes de escolher a modalidade a lista aparece inteira, e "CF Adulto"
     * de futsal e de vôlei eram rótulos idênticos.
     */
    public function test_a_opcao_mostra_a_equipe_e_a_modalidade(): void
    {
        $this->time('Clube dos Funcionários', 'CF', 'Adulto', $this->futsal);
        $this->time('Clube dos Funcionários', 'CF', 'Adulto', $this->volei);

        $this->actingAs($this->usuarioDoSetorEsporte())
            ->get(route('placar.jogos.create'))
            ->assertOk()
            ->assertSee('CF Adulto (Clube dos Funcionários · Futsal)', false)
            ->assertSee('CF Adulto (Clube dos Funcionários · Vôlei)', false);
    }
}
