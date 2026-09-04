<?php

namespace Tests\Feature\Placar;

use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Services\Placar\ScoutService;
use Database\Seeders\ModalidadeSeeder;
use Database\Seeders\PlacarDemoSeeder;
use Database\Seeders\PlacarPartidasJogadasSeeder;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * O seed de partidas jogadas existe para dar o que conferir nas telas de
 * scout. Se ele gerar placar que não fecha com o log de eventos, ou repetir
 * eventos ao rodar duas vezes, o dado de teste vira fonte de dúvida em vez de
 * referência — é isso que estes testes travam.
 */
class PartidasJogadasSeederTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $this->seed(ModalidadeSeeder::class);
        $this->seed(PlacarDemoSeeder::class);
        $this->seed(PlacarPartidasJogadasSeeder::class);
    }

    private function jogosEncerrados()
    {
        return Jogo::where('status', Jogo::STATUS_ENCERRADO)->orderBy('id')->get();
    }

    public function test_cria_partidas_encerradas_nas_tres_modalidades(): void
    {
        $slugs = $this->jogosEncerrados()
            ->load('modalidade')
            ->pluck('modalidade.slug')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame([Modalidade::BASQUETE, Modalidade::FUTSAL, Modalidade::VOLEI], $slugs);
    }

    /**
     * O cache de placar do jogo tem de bater com o recálculo a partir do log —
     * é a única garantia de que o seed não deixou o banco num estado que a API
     * nunca produziria.
     */
    public function test_placar_gravado_confere_com_o_log_de_eventos(): void
    {
        foreach ($this->jogosEncerrados() as $jogo) {
            $calculado = $jogo->calcularPlacar();

            $this->assertSame($calculado['placar_casa'], $jogo->placar_casa, "jogo #{$jogo->id}");
            $this->assertSame($calculado['placar_fora'], $jogo->placar_fora, "jogo #{$jogo->id}");
            $this->assertSame($calculado['sets_casa'], $jogo->sets_casa, "jogo #{$jogo->id}");
            $this->assertSame($calculado['sets_fora'], $jogo->sets_fora, "jogo #{$jogo->id}");
        }
    }

    public function test_futsal_rende_vitoria_derrota_e_empate_para_o_mesmo_time(): void
    {
        $futsal = Modalidade::where('slug', Modalidade::FUTSAL)->first();

        $jogos = Jogo::where('modalidade_id', $futsal->id)
            ->where('status', Jogo::STATUS_ENCERRADO)
            ->orderBy('data_hora')
            ->get();

        $this->assertCount(3, $jogos);

        $resultados = $jogos->map(fn (Jogo $j) => match (true) {
            $j->placar_casa > $j->placar_fora => 'V',
            $j->placar_casa < $j->placar_fora => 'D',
            default => 'E',
        })->all();

        $this->assertSame(['V', 'D', 'E'], $resultados);
    }

    /**
     * O ponto estornado continua na timeline (é histórico) mas sai do placar.
     * Sem o desconto, a primeira partida de futsal terminaria 5x2.
     */
    public function test_o_ponto_estornado_sai_do_placar_mas_fica_na_timeline(): void
    {
        $estorno = JogoEvento::where('tipo', JogoEvento::TIPO_ESTORNO)->first();

        $this->assertNotNull($estorno, 'o seed deveria criar pelo menos um estorno');

        $jogo = $estorno->jogo;
        $sumula = app(ScoutService::class)->sumula($jogo);

        $this->assertSame(4, $jogo->placar_casa);
        $this->assertSame(2, $jogo->placar_fora);

        $estornados = collect($sumula['eventos'])->where('estornado', true);
        $this->assertCount(1, $estornados);
        $this->assertSame(JogoEvento::TIPO_PONTO, $estornados->first()['tipo']);
    }

    public function test_basquete_soma_o_valor_da_cesta_em_vez_de_contar_eventos(): void
    {
        $basquete = Modalidade::where('slug', Modalidade::BASQUETE)->first();

        $jogo = Jogo::where('modalidade_id', $basquete->id)
            ->where('status', Jogo::STATUS_ENCERRADO)
            ->firstOrFail();

        $cestas = JogoEvento::where('jogo_id', $jogo->id)
            ->where('tipo', JogoEvento::TIPO_PONTO)
            ->get();

        // Se algum lugar contasse eventos em vez de somar `valor`, os dois
        // números baixo seriam iguais — e o teste não valeria nada.
        $this->assertGreaterThan($cestas->count(), $jogo->placar_casa + $jogo->placar_fora);
        $this->assertEqualsCanonicalizing([1, 2, 3], $cestas->pluck('valor')->unique()->sort()->values()->all());
    }

    public function test_volei_pontua_por_sets_e_nao_registra_falta(): void
    {
        $volei = Modalidade::where('slug', Modalidade::VOLEI)->first();

        $jogo = Jogo::where('modalidade_id', $volei->id)
            ->where('status', Jogo::STATUS_ENCERRADO)
            ->firstOrFail();

        $this->assertSame(3, $jogo->sets_casa);
        $this->assertSame(1, $jogo->sets_fora);

        $this->assertSame(0, JogoEvento::where('jogo_id', $jogo->id)
            ->where('tipo', JogoEvento::TIPO_FALTA)
            ->count());
    }

    public function test_escala_os_dois_times_de_cada_partida(): void
    {
        foreach ($this->jogosEncerrados() as $jogo) {
            $times = Escalacao::where('jogo_id', $jogo->id)->distinct()->pluck('time_id');

            $this->assertEqualsCanonicalizing(
                [$jogo->time_casa_id, $jogo->time_fora_id],
                $times->all(),
                "jogo #{$jogo->id}",
            );
        }
    }

    /**
     * A súmula precisa render número de camisa, placar por período e totais
     * por jogador — se o seed não produzir os três, a tela abre "montada" mas
     * sem nada para olhar.
     */
    public function test_a_sumula_do_seed_tem_numero_placar_por_periodo_e_totais(): void
    {
        $jogo = $this->jogosEncerrados()->first();
        $sumula = app(ScoutService::class)->sumula($jogo);

        $this->assertNotEmpty($sumula['placar_por_periodo']);
        $this->assertNotEmpty($sumula['totais_por_jogador']['time_casa']);
        $this->assertNotEmpty($sumula['totais_por_jogador']['time_fora']);

        $comJogador = collect($sumula['eventos'])->firstWhere('jogador', '!=', null);
        $this->assertNotNull($comJogador['jogador']['numero']);
    }

    /**
     * Substituiu o antigo teste de artilharia: o scout deixou de medir
     * ranking entre jogos e passou a medir atuação numa partida, com a
     * minutagem de cada lance.
     */
    public function test_atuacao_do_jogador_na_partida_vem_minutada(): void
    {
        $jogo = $this->jogosEncerrados()->first();

        $ponto = JogoEvento::where('jogo_id', $jogo->id)
            ->where('tipo', JogoEvento::TIPO_PONTO)
            ->whereNotNull('jogador_id')
            ->first();
        $this->assertNotNull($ponto, 'o seeder deveria gerar ponto com jogador');

        $atuacao = app(ScoutService::class)->atuacaoNaPartida($jogo, $ponto->jogador);

        $this->assertGreaterThan(0, $atuacao['totais']['pontos']);
        $this->assertNotEmpty($atuacao['lances']);

        // Todo ponto/falta precisa carregar o minuto — é o que torna a
        // ficha aproveitável pelo scout.
        foreach ($atuacao['lances'] as $lance) {
            if (in_array($lance['tipo'], JogoEvento::TIPOS_COM_MINUTAGEM, true)) {
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $lance['minuto']);
            }
        }
    }

    /** Rodar de novo não pode duplicar evento nem inflar placar. */
    public function test_rodar_o_seeder_de_novo_e_idempotente(): void
    {
        $antes = [
            'jogos' => Jogo::count(),
            'eventos' => JogoEvento::count(),
            'escalacoes' => Escalacao::count(),
            'placares' => Jogo::orderBy('id')->pluck('placar_casa')->all(),
        ];

        $this->seed(PlacarPartidasJogadasSeeder::class);

        $this->assertSame($antes['jogos'], Jogo::count());
        $this->assertSame($antes['eventos'], JogoEvento::count());
        $this->assertSame($antes['escalacoes'], Escalacao::count());
        $this->assertSame($antes['placares'], Jogo::orderBy('id')->pluck('placar_casa')->all());
    }
}
