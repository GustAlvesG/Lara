<?php

namespace Database\Seeders;

use App\Models\Placar\Competicao;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Escalacao;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Uma partida de vôlei inteira: Clube dos Funcionários 3 x 1 Flamengo.
 *
 *   php artisan db:seed --class=PlacarVoleiClubeFlamengoSeeder
 *
 * O **Flamengo é criado por completo** — equipe, time de vôlei adulto e um
 * elenco de 12 jogadores com número, posição e data de nascimento. O
 * **Clube dos Funcionários já existe** e é apenas localizado: o seeder
 * nunca cria essa equipe, para não duplicar cadastro real. O time de vôlei
 * do Clube e o elenco dele, esses sim, são criados se ainda não existirem
 * — o Clube tem futsal e basquete cadastrados, mas não vôlei.
 *
 * A partida é jogada evento a evento: 4 sets (25x20, 22x25, 25x23, 25x19),
 * com os pontos alternando entre os times na proporção da parcial em vez
 * de saírem em bloco, escalação dos dois lados, tempos técnicos,
 * substituições e um cartão. `sets_casa`/`sets_fora` saem do evento `set`
 * de cada parcial — não da contagem de pontos.
 *
 * Vôlei não tem falta (ver ModalidadeRegras::permiteFalta), então esta
 * partida não registra nenhuma, de propósito.
 *
 * Determinístico (nada de random: o mesmo comando gera sempre os mesmos
 * números) e idempotente (partida que já tem evento é pulada, então rodar
 * de novo não duplica log nem infla placar).
 */
class PlacarVoleiClubeFlamengoSeeder extends Seeder
{
    private const CATEGORIA = 'Adulto';

    /** Parciais [Clube, Flamengo]: três sets do Clube, um do Flamengo. */
    private const SETS = [[25, 20], [22, 25], [25, 23], [25, 19]];

    /**
     * Elenco na ordem em que entra em quadra: os 6 primeiros são titulares,
     * o 7º é o líbero e o resto é banco. A ordem importa — escalação e
     * cadência de pontos leem daqui.
     *
     * [nome, nome no telão, número, posição, nascimento]
     */
    private const ELENCO_CLUBE = [
        ['Rafael Menezes', 'Rafa', '1', 'Levantador', '1994-02-11'],
        ['Diego Camargo', 'Diego', '9', 'Oposto', '1996-07-03'],
        ['Vinícius Andrade', 'Vini', '7', 'Ponteiro', '1998-11-25'],
        ['Otávio Lins', 'Otávio', '12', 'Ponteiro', '1997-04-18'],
        ['Henrique Salles', 'Henrique', '5', 'Central', '1995-09-30'],
        ['Márcio Prado', 'Márcio', '14', 'Central', '1993-01-22'],
        ['Caio Ferraz', 'Caio', '3', 'Líbero', '1999-06-07'],
        ['Leandro Bastos', 'Leandro', '8', 'Ponteiro', '2001-03-14'],
        ['Fábio Queiroz', 'Fábio', '10', 'Oposto', '2000-08-09'],
        ['Danilo Rocha', 'Danilo', '6', 'Central', '1999-12-01'],
        ['Alex Moura', 'Alex', '2', 'Levantador', '2002-05-16'],
        ['Wesley Pinto', 'Wesley', '11', 'Ponteiro', '2003-10-27'],
    ];

    private const ELENCO_FLAMENGO = [
        ['Murilo Fontes', 'Murilo', '1', 'Levantador', '1993-03-08'],
        ['Thiago Bandeira', 'Thiago', '9', 'Oposto', '1995-10-19'],
        ['Igor Sampaio', 'Igor', '7', 'Ponteiro', '1997-01-05'],
        ['Renan Diniz', 'Renan', '13', 'Ponteiro', '1996-06-23'],
        ['Gustavo Peixoto', 'Gustavo', '5', 'Central', '1994-08-30'],
        ['Eduardo Marques', 'Eduardo', '15', 'Central', '1992-12-12'],
        ['Felipe Nogueira', 'Felipe', '4', 'Líbero', '1998-02-02'],
        ['Lucas Vergara', 'Lucas', '8', 'Ponteiro', '2000-09-21'],
        ['Paulo Nunes', 'Paulo', '10', 'Oposto', '1999-04-04'],
        ['Rodrigo Antunes', 'Rodrigo', '6', 'Central', '2001-07-17'],
        ['Yuri Castelo', 'Yuri', '2', 'Levantador', '2002-11-11'],
        ['Samuel Braga', 'Samuel', '11', 'Ponteiro', '2003-01-29'],
    ];

    /**
     * Quem faz o ponto, por posição no elenco, circulando: oposto (1) e
     * ponteiros (2, 3) puxam o ataque, centrais (4, 5) aparecem menos, o
     * levantador (0) pontua de bloqueio ou largada de vez em quando, e o
     * líbero (6) nunca pontua. Sem isso todos pontuariam igual e a súmula
     * ficaria com cara de dado gerado.
     */
    private const CADENCIA = [1, 2, 4, 3, 1, 5, 2, 0, 1, 3, 2, 4];

    /** Sequência do evento dentro da partida. */
    private int $sequencia = 0;

    /** Momento do evento, avança conforme a partida "anda". */
    private Carbon $relogio;

    /**
     * Elenco de cada time NA ORDEM das constantes acima — é dela que saem
     * titulares, capitão e pontuadores, sem depender da ordem em que o
     * banco devolve as linhas.
     *
     * @var array<int, list<array{jogador: Jogador, numero: string}>>
     */
    private array $elencos = [];

    /** Posição na CADENCIA de cada time (time_id => próximo índice). */
    private array $cadencia = [];

    public function run(): void
    {
        $volei = Modalidade::where('slug', Modalidade::VOLEI)->first();

        if (!$volei) {
            $this->command->error('Modalidade "volei" não encontrada — rode antes: php artisan db:seed --class=ModalidadeSeeder');

            return;
        }

        $clube = $this->equipeDoClube();

        if (!$clube) {
            $this->command->error('Equipe "Clube dos Funcionários" não encontrada. Este seeder não a cria — cadastre-a em /placar/equipes e rode de novo.');

            return;
        }

        $flamengo = Equipe::firstOrCreate(
            ['nome' => 'Flamengo'],
            [
                'nome_curto' => 'FLA',
                'cidade' => 'Rio de Janeiro',
                'criado_em_campo' => false,
                'ativo' => true,
            ],
        );

        $timeClube = $this->timeComElenco($clube, $volei, self::ELENCO_CLUBE);
        $timeFlamengo = $this->timeComElenco($flamengo, $volei, self::ELENCO_FLAMENGO);

        $competicao = Competicao::firstOrCreate(
            [
                'nome' => 'Torneio de Vôlei do Clube ' . now()->year,
                'modalidade_id' => $volei->id,
                'temporada' => now()->year,
            ],
            ['ativo' => true],
        );

        $jogo = Jogo::firstOrCreate(
            [
                'time_casa_id' => $timeClube->id,
                'time_fora_id' => $timeFlamengo->id,
                'data_hora' => now()->subDays(2)->setTime(20, 0),
            ],
            [
                'modalidade_id' => $volei->id,
                'competicao_id' => $competicao->id,
                'local' => 'Ginásio Poliesportivo do Clube dos Funcionários',
                'status' => Jogo::STATUS_AGENDADO,
                'criado_em_campo' => false,
            ],
        );

        if ($jogo->eventos()->exists()) {
            $this->command->info("Nada a fazer: o jogo #{$jogo->id} já tem eventos registrados.");
            $this->relatorio($jogo);

            return;
        }

        $this->jogarPartida($jogo);
        $this->relatorio($jogo);
    }

    /* ============================ Ciclo da partida =========================== */

    private function jogarPartida(Jogo $jogo): void
    {
        $this->sequencia = 0;
        $this->relogio = $jogo->data_hora->copy();
        $this->cadencia = [];

        $this->escalar($jogo, $jogo->timeCasa);
        $this->escalar($jogo, $jogo->timeFora);

        $jogo->update(['status' => Jogo::STATUS_AO_VIVO]);

        $this->evento($jogo, JogoEvento::TIPO_INICIO_JOGO);
        $this->evento($jogo, JogoEvento::TIPO_CRONO_PLAY, periodo: 1);

        foreach (self::SETS as $indice => [$pontosCasa, $pontosFora]) {
            $this->disputarSet($jogo, $indice + 1, $pontosCasa, $pontosFora);
        }

        $this->evento($jogo, JogoEvento::TIPO_FIM_JOGO);

        // O cache de placar sai do log — mesma conta do endpoint de
        // encerramento. O seed não pode deixar o banco num estado que a API
        // nunca produziria.
        $jogo->update([
            ...$jogo->calcularPlacar(),
            'status' => Jogo::STATUS_ENCERRADO,
            'periodos_jogados' => count(self::SETS),
        ]);
    }

    /** Seis titulares em quadra (vôlei), líbero e banco fora, capitão no levantador. */
    private function escalar(Jogo $jogo, Time $time): void
    {
        foreach ($this->elencos[$time->id] as $indice => $item) {
            Escalacao::firstOrCreate(
                ['jogo_id' => $jogo->id, 'jogador_id' => $item['jogador']->id],
                [
                    'time_id' => $time->id,
                    'numero' => $item['numero'],
                    'titular' => $indice < 6,
                    'capitao' => $indice === 0,
                ],
            );
        }
    }

    /**
     * Um set inteiro: a parcial ponto a ponto, o tempo pedido por quem está
     * atrás, e o evento `set` de quem levou.
     */
    private function disputarSet(Jogo $jogo, int $numero, int $pontosCasa, int $pontosFora): void
    {
        $this->evento($jogo, JogoEvento::TIPO_CRONO_SET, periodo: $numero, valor: $numero);

        $casaVenceu = $pontosCasa > $pontosFora;
        $vencedor = $casaVenceu ? $jogo->timeCasa : $jogo->timeFora;
        $perdedor = $casaVenceu ? $jogo->timeFora : $jogo->timeCasa;

        $rallies = $this->rallies($pontosCasa, $pontosFora);
        $meio = intdiv(count($rallies), 2);

        foreach ($rallies as $i => $pontoDaCasa) {
            $time = $pontoDaCasa ? $jogo->timeCasa : $jogo->timeFora;

            $this->evento(
                $jogo,
                JogoEvento::TIPO_PONTO,
                time: $time,
                jogador: $this->proximoPontuador($time),
                valor: 1,
                periodo: $numero,
            );

            if ($i === $meio) {
                $this->evento($jogo, JogoEvento::TIPO_TIMEOUT, time: $perdedor, periodo: $numero);
            }
        }

        // Uma troca por set a partir do segundo e um cartão no terceiro: é
        // o que dá à timeline algo além de ponto.
        if ($numero >= 2) {
            $this->substituicao($jogo, $numero % 2 === 0 ? $jogo->timeCasa : $jogo->timeFora, $numero);
        }

        if ($numero === 3) {
            $this->cartao($jogo, $jogo->timeFora, $numero);
        }

        // É o `set` que conta em sets_casa/sets_fora — não a contagem de
        // pontos da parcial.
        $this->evento($jogo, JogoEvento::TIPO_SET, time: $vencedor, periodo: $numero, valor: $numero);
    }

    /**
     * Ordem em que os pontos do set caem: alternando entre os times na
     * proporção da parcial, e não 25 de um seguidos de 20 do outro. O
     * último ponto é sempre de quem venceu — é ele que fecha o set.
     *
     * @return list<bool> true = ponto do time da casa
     */
    private function rallies(int $pontosCasa, int $pontosFora): array
    {
        $casaVenceu = $pontosCasa > $pontosFora;

        // O ponto que fecha o set sai da distribuição e volta no fim.
        $alvoCasa = $casaVenceu ? $pontosCasa - 1 : $pontosCasa;
        $alvoFora = $casaVenceu ? $pontosFora : $pontosFora - 1;

        $ordem = [];
        $feitosCasa = $feitosFora = 0;

        while ($feitosCasa < $alvoCasa || $feitosFora < $alvoFora) {
            $daCasa = $feitosFora >= $alvoFora
                || ($feitosCasa < $alvoCasa
                    && ($feitosCasa + 1) / max($alvoCasa, 1) <= ($feitosFora + 1) / max($alvoFora, 1));

            $ordem[] = $daCasa;
            $daCasa ? $feitosCasa++ : $feitosFora++;
        }

        $ordem[] = $casaVenceu;

        return $ordem;
    }

    /* ============================== Eventos ================================= */

    private function substituicao(Jogo $jogo, Time $time, int $periodo): void
    {
        $sai = $this->jogadorPorOrdem($time, 3);
        $entra = $this->jogadorPorOrdem($time, 7);

        $this->evento(
            $jogo,
            JogoEvento::TIPO_SUBSTITUICAO,
            time: $time,
            jogador: $entra,
            periodo: $periodo,
            payload: ['sai_jogador_id' => $sai->id, 'entra_jogador_id' => $entra->id],
        );
    }

    private function cartao(Jogo $jogo, Time $time, int $periodo): void
    {
        $this->evento(
            $jogo,
            JogoEvento::TIPO_CARTAO,
            time: $time,
            jogador: $this->jogadorPorOrdem($time, 1),
            periodo: $periodo,
            payload: ['cor' => 'amarelo', 'motivo' => 'reclamação com a arbitragem'],
        );
    }

    private function evento(
        Jogo $jogo,
        string $tipo,
        ?Time $time = null,
        ?Jogador $jogador = null,
        ?int $valor = null,
        ?int $periodo = null,
        ?array $payload = null,
    ): JogoEvento {
        // 25s por rally: os quatro sets dão pouco mais de 1h20 de partida,
        // que é o tempo plausível de um 3x1. O cronômetro anda junto com o
        // relógio, então a timeline sai ordenada e com minutagem coerente.
        $this->relogio = $this->relogio->copy()->addSeconds(25);
        $this->sequencia++;

        return JogoEvento::create([
            'uuid' => (string) Str::uuid(),
            'jogo_id' => $jogo->id,
            'sequencia' => $this->sequencia,
            'tipo' => $tipo,
            'time_id' => $time?->id,
            'jogador_id' => $jogador?->id,
            'valor' => $valor,
            'periodo' => $periodo,
            'cronometro_ms' => $this->sequencia * 25_000,
            'ocorrido_em' => $this->relogio,
            'payload' => $payload,
        ]);
    }

    /** Próximo pontuador do time, seguindo a CADENCIA — sem sorteio. */
    private function proximoPontuador(Time $time): Jogador
    {
        $posicao = $this->cadencia[$time->id] ?? 0;
        $this->cadencia[$time->id] = $posicao + 1;

        return $this->jogadorPorOrdem($time, self::CADENCIA[$posicao % count(self::CADENCIA)]);
    }

    /** Jogador pela posição na lista do elenco (0 = levantador, 6 = líbero). */
    private function jogadorPorOrdem(Time $time, int $ordem): Jogador
    {
        $elenco = $this->elencos[$time->id];

        return $elenco[$ordem % count($elenco)]['jogador'];
    }

    /* ============================== Cadastro ================================ */

    /**
     * O Clube dos Funcionários já existe no cadastro — este seeder só o
     * localiza. Procura pelo nome, pelo nome curto e, por último, por
     * "Funcion", que casa com ou sem acento.
     */
    private function equipeDoClube(): ?Equipe
    {
        return Equipe::where('nome', 'Clube dos Funcionários')
            ->orWhere('nome_curto', 'CF')
            ->orWhere('nome', 'like', '%Funcion%')
            ->orderBy('id')
            ->first();
    }

    /**
     * Time de vôlei adulto da equipe, com o elenco da temporada corrente.
     * Reaproveita o que já existir: o jogador é procurado pelo nome dentro
     * da equipe + modalidade dele (é de uma só de cada), e o vínculo é
     * firstOrCreate. Rodar de novo não cria segundo time, segundo jogador
     * nem segundo vínculo.
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string}>  $elenco
     */
    private function timeComElenco(Equipe $equipe, Modalidade $modalidade, array $elenco): Time
    {
        $time = Time::firstOrCreate(
            ['equipe_id' => $equipe->id, 'modalidade_id' => $modalidade->id, 'categoria' => self::CATEGORIA],
            ['criado_em_campo' => false, 'ativo' => true],
        );

        $ordenado = [];

        foreach ($elenco as [$nome, $exibicao, $numero, $posicao, $nascimento]) {
            $jogador = Jogador::firstOrCreate(
                [
                    'nome' => $nome,
                    'equipe_id' => $equipe->id,
                    'modalidade_id' => $modalidade->id,
                ],
                [
                    'nome_exibicao' => $exibicao,
                    'data_nascimento' => $nascimento,
                    'criado_em_campo' => false,
                    'ativo' => true,
                ],
            );

            Elenco::firstOrCreate(
                ['time_id' => $time->id, 'jogador_id' => $jogador->id, 'temporada' => now()->year],
                ['numero' => $numero, 'posicao' => $posicao, 'ativo' => true],
            );

            $ordenado[] = ['jogador' => $jogador, 'numero' => $numero];
        }

        $this->elencos[$time->id] = $ordenado;

        return $time;
    }

    /* =============================== Relatório ============================== */

    private function relatorio(Jogo $jogo): void
    {
        $jogo->refresh()->load(['timeCasa.equipe', 'timeFora.equipe']);

        $this->command->newLine();
        $this->command->info(sprintf(
            'Jogo #%d — %s %d x %d %s (sets)',
            $jogo->id,
            $jogo->timeCasa->nomeExibicaoResolvido(),
            $jogo->sets_casa,
            $jogo->sets_fora,
            $jogo->timeFora->nomeExibicaoResolvido(),
        ));
        $this->command->line("  Pontos somados nos 4 sets: {$jogo->placar_casa} x {$jogo->placar_fora}");
        $this->command->line('  Parciais: ' . implode(', ', array_map(
            fn (array $set) => "{$set[0]}x{$set[1]}",
            self::SETS,
        )));

        $this->command->newLine();
        $this->command->line('Para conferir:');
        $this->command->line("  Web   /placar/scout/jogos/{$jogo->id}/sumula");
        $this->command->line("  Web   /placar/scout/jogos/{$jogo->id}/sumula?time_id={$jogo->time_casa_id}   (só o Clube)");
        $this->command->line("  Web   /placar/scout/jogos/{$jogo->id}/sumula?time_id={$jogo->time_fora_id}   (só o Flamengo)");
        $this->command->line("  API   GET /api/placar/jogos/{$jogo->id}/sumula");
        $this->command->newLine();
    }
}
