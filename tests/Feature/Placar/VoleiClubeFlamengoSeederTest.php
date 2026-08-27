<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ScoutService;
use Database\Seeders\ModalidadeSeeder;
use Database\Seeders\PlacarVoleiClubeFlamengoSeeder;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * O seed do Clube dos Funcionários 3 x 1 Flamengo tem de produzir um jogo
 * que a API também produziria: placar em cache batendo com o log de
 * eventos, sets vindos do evento `set`, e nada de falta (vôlei não tem).
 *
 * O Flamengo é criado inteiro pelo seeder; o Clube dos Funcionários já
 * existe e só é localizado — duplicar a equipe real seria o pior estrago
 * que este seed poderia fazer.
 */
class VoleiClubeFlamengoSeederTest extends TestCase
{
    use MigratesPlacarSchema;

    private Equipe $clube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();
        $this->seed(ModalidadeSeeder::class);

        // O cadastro do Clube é pré-existente — é essa a premissa do seed.
        $this->clube = Equipe::create([
            'nome' => 'Clube dos Funcionários', 'nome_curto' => 'CF',
            'cidade' => 'Volta Redonda', 'ativo' => true,
        ]);
    }

    private function semear(): void
    {
        $this->seed(PlacarVoleiClubeFlamengoSeeder::class);
    }

    private function jogo(): Jogo
    {
        return Jogo::firstOrFail();
    }

    public function test_cria_o_flamengo_inteiro_equipe_time_e_elenco(): void
    {
        $this->semear();

        $flamengo = Equipe::where('nome', 'Flamengo')->first();
        $this->assertNotNull($flamengo);
        $this->assertSame('FLA', $flamengo->nome_curto);

        $time = Time::where('equipe_id', $flamengo->id)->first();
        $this->assertSame(Modalidade::where('slug', 'volei')->first()->id, $time->modalidade_id);
        $this->assertSame('Adulto', $time->categoria);

        $this->assertSame(12, $time->elencos()->count());

        // Número, posição e nascimento preenchidos — é o cadastro completo,
        // não só o nome.
        $capitao = Elenco::where('time_id', $time->id)->where('numero', '1')->first();
        $this->assertSame('Levantador', $capitao->posicao);
        $this->assertNotNull($capitao->jogador->data_nascimento);
        $this->assertGreaterThan(18, $capitao->jogador->idade());
    }

    /** Duplicar a equipe real do Clube seria o pior estrago possível aqui. */
    public function test_reaproveita_o_clube_que_ja_existia(): void
    {
        $this->semear();

        $this->assertSame(1, Equipe::where('nome', 'Clube dos Funcionários')->count());
        $this->assertSame($this->clube->id, $this->jogo()->timeCasa->equipe_id);
    }

    /** Sem o Clube cadastrado o seed não inventa a equipe — ele para. */
    public function test_sem_o_clube_cadastrado_nao_cria_nada(): void
    {
        $this->clube->forceDelete();

        $this->semear();

        $this->assertSame(0, Equipe::count());
        $this->assertSame(0, Jogo::count());
    }

    public function test_termina_tres_sets_a_um_para_o_clube(): void
    {
        $this->semear();

        $jogo = $this->jogo();

        $this->assertSame(Jogo::STATUS_ENCERRADO, $jogo->status);
        $this->assertSame(3, $jogo->sets_casa);
        $this->assertSame(1, $jogo->sets_fora);
        $this->assertSame(4, $jogo->periodos_jogados);
    }

    /** O cache do jogo tem de bater com o recálculo a partir do log. */
    public function test_placar_gravado_confere_com_o_log_de_eventos(): void
    {
        $this->semear();

        $jogo = $this->jogo();
        $calculado = $jogo->calcularPlacar();

        $this->assertSame($calculado['sets_casa'], $jogo->sets_casa);
        $this->assertSame($calculado['sets_fora'], $jogo->sets_fora);
        // Soma das parciais: 25+22+25+25 e 20+25+23+19.
        $this->assertSame(97, $jogo->placar_casa);
        $this->assertSame(87, $jogo->placar_fora);
        $this->assertSame($calculado['placar_casa'], $jogo->placar_casa);
    }

    /** Cada parcial fecha com o ponto e o evento `set` de quem venceu. */
    public function test_cada_set_e_fechado_pelo_time_que_o_venceu(): void
    {
        $this->semear();

        $jogo = $this->jogo();
        $casa = $jogo->time_casa_id;
        $fora = $jogo->time_fora_id;

        $vencedores = $jogo->eventos()->where('tipo', JogoEvento::TIPO_SET)->pluck('time_id')->all();
        $this->assertSame([$casa, $fora, $casa, $casa], $vencedores);

        foreach ([1 => $casa, 2 => $fora, 3 => $casa, 4 => $casa] as $set => $vencedor) {
            $ultimoPonto = $jogo->eventos()
                ->where('tipo', JogoEvento::TIPO_PONTO)
                ->where('periodo', $set)
                ->orderByDesc('sequencia')
                ->first();

            $this->assertSame($vencedor, $ultimoPonto->time_id, "set {$set}");
        }
    }

    /** Os pontos alternam entre os times — um set não é bloco de um lado só. */
    public function test_os_pontos_do_set_alternam_entre_os_times(): void
    {
        $this->semear();

        $jogo = $this->jogo();
        $sequenciaDoPrimeiroSet = $jogo->eventos()
            ->where('tipo', JogoEvento::TIPO_PONTO)
            ->where('periodo', 1)
            ->pluck('time_id');

        $trocasDePosse = $sequenciaDoPrimeiroSet
            ->sliding(2)
            ->filter(fn ($par) => $par->first() !== $par->last())
            ->count();

        // Em bloco haveria uma troca só; aqui o set vai e volta.
        $this->assertGreaterThan(10, $trocasDePosse);
    }

    /** Vôlei não tem falta (ModalidadeRegras::permiteFalta). */
    public function test_nao_registra_falta(): void
    {
        $this->semear();

        $this->assertSame(0, $this->jogo()->eventos()->where('tipo', JogoEvento::TIPO_FALTA)->count());
    }

    /** Vôlei entra com seis em quadra, não com cinco. */
    public function test_escala_seis_titulares_e_um_capitao_por_time(): void
    {
        $this->semear();

        $jogo = $this->jogo();

        foreach ([$jogo->time_casa_id, $jogo->time_fora_id] as $timeId) {
            $escalacao = Escalacao::where('jogo_id', $jogo->id)->where('time_id', $timeId)->get();

            $this->assertCount(12, $escalacao);
            $this->assertSame(6, $escalacao->where('titular', true)->count());
            $this->assertSame(1, $escalacao->where('capitao', true)->count());
        }
    }

    /** O líbero não pontua; oposto e ponteiros puxam o ataque. */
    public function test_os_pontos_se_espalham_pelo_elenco_sem_passar_pelo_libero(): void
    {
        $this->semear();

        $jogo = $this->jogo();
        $atuacao = app(ScoutService::class)->sumula($jogo, $jogo->time_casa_id);
        $totais = collect($atuacao['totais_por_jogador']['time_casa']);

        $this->assertGreaterThanOrEqual(5, $totais->count());

        $libero = Elenco::where('time_id', $jogo->time_casa_id)->where('posicao', 'Líbero')->first();
        $this->assertFalse($totais->contains('jogador_id', $libero->jogador_id));

        // O melhor pontuador do time é o oposto ou um ponteiro.
        $artilheiro = $totais->sortByDesc('pontos')->first();
        $posicao = Elenco::where('time_id', $jogo->time_casa_id)
            ->where('jogador_id', $artilheiro['jogador_id'])
            ->value('posicao');
        $this->assertContains($posicao, ['Oposto', 'Ponteiro']);
    }

    /** A súmula recortada é o uso mais imediato deste seed. */
    public function test_a_sumula_do_flamengo_traz_so_os_pontos_do_flamengo(): void
    {
        $this->semear();

        $jogo = $this->jogo();
        $sumula = app(ScoutService::class)->sumula($jogo, $jogo->time_fora_id);

        $this->assertSame('fora', $sumula['recorte']['lado']);
        $this->assertSame([], $sumula['totais_por_jogador']['time_casa']);

        $pontos = collect($sumula['totais_por_jogador']['time_fora'])->sum('pontos');
        $this->assertSame(87, $pontos);
    }

    public function test_rodar_de_novo_nao_duplica_nada(): void
    {
        $this->semear();

        $eventos = JogoEvento::count();
        $jogadores = Jogador::count();

        $this->semear();

        $this->assertSame(1, Jogo::count());
        $this->assertSame($eventos, JogoEvento::count());
        $this->assertSame($jogadores, Jogador::count());
        $this->assertSame(3, $this->jogo()->sets_casa);
    }
}
