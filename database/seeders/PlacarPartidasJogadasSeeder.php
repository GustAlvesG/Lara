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
 * Partidas já jogadas do Placar Clube — escalação, log de eventos e jogo
 * encerrado.
 *
 * O PlacarDemoSeeder cria cadastro e três jogos AGENDADOS, o que é suficiente
 * para o Node selecionar um jogo e começar a operar. Só que toda a camada de
 * scout (súmula, artilharia, perfil do jogador, painel do time) lê de
 * `jogo_eventos` — sem evento nenhum, essas quatro telas abrem vazias e não há
 * como conferir se estão certas. É essa lacuna que este seeder preenche.
 *
 *   php artisan db:seed --class=PlacarPartidasJogadasSeeder
 *
 * O que ele produz, de propósito:
 *
 * - **Futsal**: 3 partidas encerradas entre os mesmos times, com resultados
 *   diferentes (vitória, derrota e empate) — é o que faz o retrospecto V/D/E
 *   do painel do time mostrar as três situações.
 * - **Basquete**: 1 partida em 4 quartos com cestas de 1, 2 e 3 pontos, para
 *   validar que `valor` é somado e não contado.
 * - **Vôlei**: 1 partida por sets, único caminho que preenche
 *   `sets_casa`/`sets_fora` (o cadastro de demonstração não tem time de vôlei,
 *   então este seeder cria o par).
 * - Faltas, cartão, substituição e timeout na timeline.
 * - Um **estorno** na primeira partida de futsal: o ponto estornado continua
 *   na linha do tempo marcado como tal, mas sai do placar e dos totais. É o
 *   detalhe mais fácil de quebrar sem ninguém perceber.
 *
 * Determinístico: nada de random, o mesmo comando gera sempre os mesmos
 * números. Idempotente: partida que já tem evento é pulada, então rodar de
 * novo não duplica log nem infla placar.
 */
class PlacarPartidasJogadasSeeder extends Seeder
{
    /** Sequência do evento dentro da partida sendo montada. */
    private int $sequencia = 0;

    /** Momento do evento sendo montado, avança conforme o jogo "anda". */
    private Carbon $relogio;

    public function run(): void
    {
        $futsal = Modalidade::where('slug', Modalidade::FUTSAL)->first();
        $basquete = Modalidade::where('slug', Modalidade::BASQUETE)->first();
        $volei = Modalidade::where('slug', Modalidade::VOLEI)->first();

        if (!$futsal || !$basquete || !$volei) {
            $this->command->error('Modalidades não encontradas — rode o ModalidadeSeeder antes.');

            return;
        }

        $equipes = Equipe::orderBy('id')->take(2)->get();

        if ($equipes->count() < 2) {
            $this->command->error('Cadastro de demonstração ausente — rode antes: php artisan db:seed --class=PlacarDemoSeeder');

            return;
        }

        [$equipeCasa, $equipeFora] = [$equipes[0], $equipes[1]];

        $resumo = [];

        $resumo[] = $this->futsal($futsal, $equipeCasa, $equipeFora);
        $resumo[] = $this->basquete($basquete, $equipeCasa, $equipeFora);
        $resumo[] = $this->volei($volei, $equipeCasa, $equipeFora);

        $this->relatorio(array_filter(array_merge(...$resumo)));
    }

    /* ================================ Futsal ================================ */

    /**
     * Três partidas entre os mesmos times, com placar fechado de propósito
     * para dar vitória, derrota e empate ao time da casa.
     *
     * @return list<Jogo>
     */
    private function futsal(Modalidade $modalidade, Equipe $casa, Equipe $fora): array
    {
        $timeCasa = $this->time($casa, $modalidade, Time::CATEGORIA_PADRAO);
        $timeFora = $this->time($fora, $modalidade, Time::CATEGORIA_PADRAO);

        $competicao = Competicao::firstOrCreate(
            ['nome' => 'Copa Clube 2026', 'modalidade_id' => $modalidade->id, 'temporada' => 2026],
            ['ativo' => true],
        );

        // [gols da casa, gols de fora, dias atrás] — 4x2 vitória, 1x3 derrota, 2x2 empate.
        $roteiros = [[4, 2, 21], [1, 3, 14], [2, 2, 7]];
        $jogos = [];

        foreach ($roteiros as $indice => [$golsCasa, $golsFora, $diasAtras]) {
            $jogo = $this->jogo(
                $modalidade,
                $timeCasa,
                $timeFora,
                $competicao,
                now()->subDays($diasAtras)->setTime(19, 30),
                'Quadra Poliesportiva do Clube',
            );

            if ($this->jaJogado($jogo)) {
                continue;
            }

            $this->abrirPartida($jogo);

            // 2 tempos. Os gols são distribuídos alternadamente entre os
            // períodos para o placar por período não ficar todo num só.
            foreach ([1, 2] as $periodo) {
                $this->evento($jogo, JogoEvento::TIPO_PERIODO, periodo: $periodo, valor: $periodo);

                $this->pontos($jogo, $timeCasa, $periodo, $this->metade($golsCasa, $periodo), valor: 1);
                $this->pontos($jogo, $timeFora, $periodo, $this->metade($golsFora, $periodo), valor: 1);

                $this->faltas($jogo, $timeCasa, $periodo, 2);
                $this->faltas($jogo, $timeFora, $periodo, 1);
            }

            $this->evento($jogo, JogoEvento::TIPO_TIMEOUT, time: $timeCasa, periodo: 2);
            $this->substituicao($jogo, $timeFora, periodo: 2);
            $this->cartao($jogo, $timeFora, periodo: 2, cor: 'amarelo');

            // Só na primeira partida: um gol a mais do time da casa e o
            // estorno que o cancela. O placar final tem de continuar 4x2.
            if ($indice === 0) {
                $anulado = $this->pontos($jogo, $timeCasa, 2, 1, valor: 1)[0];
                $this->estorno($jogo, $anulado, 'gol anulado por impedimento na origem');
            }

            $this->encerrarPartida($jogo);
            $jogos[] = $jogo;
        }

        return $jogos;
    }

    /* =============================== Basquete =============================== */

    /**
     * Uma partida em 4 quartos com cestas de 1, 2 e 3 pontos — o caso em que
     * somar `valor` e contar eventos dão resultados diferentes.
     *
     * @return list<Jogo>
     */
    private function basquete(Modalidade $modalidade, Equipe $casa, Equipe $fora): array
    {
        $timeCasa = $this->time($casa, $modalidade, Time::CATEGORIA_PADRAO);
        $timeFora = $this->time($fora, $modalidade, Time::CATEGORIA_PADRAO);

        $jogo = $this->jogo(
            $modalidade,
            $timeCasa,
            $timeFora,
            null,
            now()->subDays(10)->setTime(20, 0),
            'Ginásio Municipal',
        );

        if ($this->jaJogado($jogo)) {
            return [];
        }

        $this->abrirPartida($jogo);

        // Cestas por quarto: [valor do ponto, quantas da casa, quantas de fora].
        $cestasPorQuarto = [
            1 => [[2, 5, 4], [3, 2, 1], [1, 2, 3]],
            2 => [[2, 4, 6], [3, 1, 2], [1, 3, 1]],
            3 => [[2, 6, 5], [3, 3, 1], [1, 1, 2]],
            4 => [[2, 5, 5], [3, 2, 3], [1, 4, 2]],
        ];

        foreach ($cestasPorQuarto as $periodo => $cestas) {
            $this->evento($jogo, JogoEvento::TIPO_PERIODO, periodo: $periodo, valor: $periodo);

            foreach ($cestas as [$valor, $qtdCasa, $qtdFora]) {
                $this->pontos($jogo, $timeCasa, $periodo, $qtdCasa, $valor);
                $this->pontos($jogo, $timeFora, $periodo, $qtdFora, $valor);
            }

            $this->faltas($jogo, $timeCasa, $periodo, 2);
            $this->faltas($jogo, $timeFora, $periodo, 2);
        }

        $this->evento($jogo, JogoEvento::TIPO_TIMEOUT, time: $timeFora, periodo: 4);
        $this->substituicao($jogo, $timeCasa, periodo: 3);

        $this->encerrarPartida($jogo);

        return [$jogo];
    }

    /* ================================ Vôlei ================================= */

    /**
     * Partida por sets — 3 a 1 para a casa. É o único caminho que preenche
     * `sets_casa`/`sets_fora`; o cadastro de demonstração não tem time de
     * vôlei, então o par é criado aqui.
     *
     * Vôlei não aceita falta (ver ModalidadeRegras::permiteFalta), então esta
     * partida não tem nenhuma — de propósito.
     *
     * @return list<Jogo>
     */
    private function volei(Modalidade $modalidade, Equipe $casa, Equipe $fora): array
    {
        $timeCasa = $this->timeComElenco($casa, $modalidade, Time::CATEGORIA_PADRAO);
        $timeFora = $this->timeComElenco($fora, $modalidade, Time::CATEGORIA_PADRAO);

        $jogo = $this->jogo(
            $modalidade,
            $timeCasa,
            $timeFora,
            null,
            now()->subDays(5)->setTime(19, 0),
            'Quadra Coberta do Clube',
        );

        if ($this->jaJogado($jogo)) {
            return [];
        }

        $this->abrirPartida($jogo);

        // [pontos da casa, pontos de fora] por set. Quem chega a 25 leva o set.
        $sets = [[25, 20], [23, 25], [25, 22], [25, 18]];

        foreach ($sets as $indice => [$pontosCasa, $pontosFora]) {
            $numeroDoSet = $indice + 1;

            $this->evento($jogo, JogoEvento::TIPO_CRONO_SET, periodo: $numeroDoSet, valor: $numeroDoSet);

            $this->pontos($jogo, $timeCasa, $numeroDoSet, $pontosCasa, valor: 1);
            $this->pontos($jogo, $timeFora, $numeroDoSet, $pontosFora, valor: 1);

            // O `set` marca quem levou — é o que calcularPlacar() conta em
            // sets_casa/sets_fora, e não a contagem de pontos do set.
            $vencedor = $pontosCasa > $pontosFora ? $timeCasa : $timeFora;
            $this->evento($jogo, JogoEvento::TIPO_SET, time: $vencedor, periodo: $numeroDoSet, valor: $numeroDoSet);
        }

        $this->evento($jogo, JogoEvento::TIPO_TIMEOUT, time: $timeFora, periodo: 2);
        $this->substituicao($jogo, $timeCasa, periodo: 3);

        $this->encerrarPartida($jogo);

        return [$jogo];
    }

    /* ============================ Ciclo da partida =========================== */

    private function jaJogado(Jogo $jogo): bool
    {
        return $jogo->eventos()->exists();
    }

    /**
     * Escala os dois times e registra o início. A escalação existe para o
     * número da camisa vir dela (e não do elenco da temporada) nas visões de
     * scout — é a prioridade que Jogo::elencoOperacionalDoTime() aplica.
     */
    private function abrirPartida(Jogo $jogo): void
    {
        $this->sequencia = 0;
        $this->relogio = $jogo->data_hora->copy();

        $this->escalar($jogo, $jogo->timeCasa);
        $this->escalar($jogo, $jogo->timeFora);

        $jogo->update(['status' => Jogo::STATUS_AO_VIVO]);

        $this->evento($jogo, JogoEvento::TIPO_INICIO_JOGO);
        $this->evento($jogo, JogoEvento::TIPO_CRONO_PLAY, periodo: 1);
    }

    /** Cinco titulares e o resto no banco, capitão na camisa 1. */
    private function escalar(Jogo $jogo, Time $time): void
    {
        $elenco = $time->elencoDaTemporada()->with('jogador')->get();

        foreach ($elenco->values() as $indice => $item) {
            Escalacao::firstOrCreate(
                ['jogo_id' => $jogo->id, 'jogador_id' => $item->jogador_id],
                [
                    'time_id' => $time->id,
                    'numero' => $item->numero ?? (string) ($indice + 1),
                    'titular' => $indice < 5,
                    'capitao' => $indice === 0,
                ],
            );
        }
    }

    /**
     * Encerra e sincroniza o cache de placar com o log — mesma conta que o
     * endpoint de encerramento faz, para o seed não deixar o banco num estado
     * que a API nunca produziria.
     */
    private function encerrarPartida(Jogo $jogo): void
    {
        $this->evento($jogo, JogoEvento::TIPO_FIM_JOGO);

        $placar = $jogo->calcularPlacar();

        $jogo->update([
            ...$placar,
            'status' => Jogo::STATUS_ENCERRADO,
            'periodos_jogados' => $jogo->eventos()
                ->whereIn('tipo', [JogoEvento::TIPO_PERIODO, JogoEvento::TIPO_SET])
                ->count(),
        ]);
    }

    /* ============================== Eventos ================================= */

    /**
     * @return list<JogoEvento> os pontos criados, em ordem
     */
    private function pontos(Jogo $jogo, Time $time, int $periodo, int $quantidade, int $valor): array
    {
        $criados = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $criados[] = $this->evento(
                $jogo,
                JogoEvento::TIPO_PONTO,
                time: $time,
                jogador: $this->jogadorDaVez($jogo, $time, $i),
                valor: $valor,
                periodo: $periodo,
            );
        }

        return $criados;
    }

    private function faltas(Jogo $jogo, Time $time, int $periodo, int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            $this->evento(
                $jogo,
                JogoEvento::TIPO_FALTA,
                time: $time,
                // Deslocado do índice dos pontos para as faltas não caírem
                // sempre no mesmo jogador que marcou.
                jogador: $this->jogadorDaVez($jogo, $time, $i + 3),
                periodo: $periodo,
            );
        }
    }

    private function cartao(Jogo $jogo, Time $time, int $periodo, string $cor): void
    {
        $this->evento(
            $jogo,
            JogoEvento::TIPO_CARTAO,
            time: $time,
            jogador: $this->jogadorDaVez($jogo, $time, 2),
            periodo: $periodo,
            payload: ['cor' => $cor],
        );
    }

    private function substituicao(Jogo $jogo, Time $time, int $periodo): void
    {
        $sai = $this->jogadorDaVez($jogo, $time, 1);
        $entra = $this->jogadorDaVez($jogo, $time, 7);

        $this->evento(
            $jogo,
            JogoEvento::TIPO_SUBSTITUICAO,
            time: $time,
            jogador: $entra,
            periodo: $periodo,
            payload: ['sai_jogador_id' => $sai?->id, 'entra_jogador_id' => $entra?->id],
        );
    }

    /** O estorno referencia o uuid do evento original em `payload.evento_uuid`. */
    private function estorno(Jogo $jogo, JogoEvento $original, string $motivo): void
    {
        $this->evento(
            $jogo,
            JogoEvento::TIPO_ESTORNO,
            time: $original->time,
            periodo: $original->periodo,
            payload: ['evento_uuid' => $original->uuid, 'motivo' => $motivo],
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
        // 40s de jogo entre eventos: o cronômetro cresce junto com o relógio,
        // então a timeline sai ordenada e com tempos plausíveis.
        $this->relogio = $this->relogio->copy()->addSeconds(40);
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
            'cronometro_ms' => $this->sequencia * 40_000,
            'ocorrido_em' => $this->relogio,
            'payload' => $payload,
        ]);
    }

    /**
     * Jogador do time por posição no elenco, circulando com módulo — é o que
     * espalha pontos e faltas por vários jogadores sem sorteio, mantendo o
     * resultado igual a cada execução.
     */
    private function jogadorDaVez(Jogo $jogo, Time $time, int $indice): ?Jogador
    {
        $elenco = $jogo->elencoOperacionalDoTime($time);

        if ($elenco->isEmpty()) {
            return null;
        }

        return $elenco[$indice % $elenco->count()]['jogador'];
    }

    /** Distribui um total entre 2 períodos: o resto sobra para o primeiro. */
    private function metade(int $total, int $periodo): int
    {
        $primeiro = (int) ceil($total / 2);

        return $periodo === 1 ? $primeiro : $total - $primeiro;
    }

    /* ============================== Cadastro ================================ */

    private function time(Equipe $equipe, Modalidade $modalidade, string $categoria): Time
    {
        return Time::firstOrCreate(
            ['equipe_id' => $equipe->id, 'modalidade_id' => $modalidade->id, 'categoria' => $categoria],
            ['criado_em_campo' => false, 'ativo' => true],
        );
    }

    /**
     * Time com elenco garantido. Só o vôlei precisa disso: os times de futsal
     * e basquete já vêm com elenco do PlacarDemoSeeder.
     */
    private function timeComElenco(Equipe $equipe, Modalidade $modalidade, string $categoria): Time
    {
        $time = $this->time($equipe, $modalidade, $categoria);

        if ($time->elencos()->count() >= 10) {
            return $time;
        }

        for ($numero = 1; $numero <= 10; $numero++) {
            // Jogador é de uma equipe e uma modalidade só — as do time.
            $jogador = Jogador::firstOrCreate(
                ['nome' => "{$equipe->nome_curto} Vôlei {$numero}"],
                [
                    'equipe_id' => $time->equipe_id,
                    'modalidade_id' => $time->modalidade_id,
                    'criado_em_campo' => false,
                    'ativo' => true,
                ],
            );

            Elenco::firstOrCreate(
                ['time_id' => $time->id, 'jogador_id' => $jogador->id, 'temporada' => now()->year],
                ['numero' => (string) $numero, 'ativo' => true],
            );
        }

        return $time;
    }

    private function jogo(
        Modalidade $modalidade,
        Time $casa,
        Time $fora,
        ?Competicao $competicao,
        Carbon $dataHora,
        string $local,
    ): Jogo {
        return Jogo::firstOrCreate(
            ['time_casa_id' => $casa->id, 'time_fora_id' => $fora->id, 'data_hora' => $dataHora],
            [
                'modalidade_id' => $modalidade->id,
                'competicao_id' => $competicao?->id,
                'local' => $local,
                'status' => Jogo::STATUS_AGENDADO,
                'criado_em_campo' => false,
            ],
        );
    }

    /* =============================== Relatório ============================== */

    /** @param list<Jogo> $jogos */
    private function relatorio(array $jogos): void
    {
        $this->command->newLine();

        if ($jogos === []) {
            $this->command->info('Nada a fazer: as partidas deste seed já tinham eventos registrados.');

            return;
        }

        $this->command->info('Partidas jogadas criadas:');

        foreach ($jogos as $jogo) {
            $jogo->refresh()->load(['modalidade', 'timeCasa', 'timeFora']);

            $sets = $jogo->sets_casa || $jogo->sets_fora
                ? "  (sets {$jogo->sets_casa}x{$jogo->sets_fora})"
                : '';

            $this->command->line(sprintf(
                '  Jogo #%-4d %-9s %s %d x %d %s%s',
                $jogo->id,
                $jogo->modalidade->slug,
                $jogo->timeCasa->nomeExibicaoResolvido(),
                $jogo->placar_casa,
                $jogo->placar_fora,
                $jogo->timeFora->nomeExibicaoResolvido(),
                $sets,
            ));
        }

        $primeiro = $jogos[0]->id;

        $this->command->newLine();
        $this->command->line('Para conferir:');
        $this->command->line("  Web   /placar/scout/jogos/{$primeiro}/sumula");
        $this->command->line('  Web   /placar/scout/jogos');
        $this->command->line("  API   GET /api/placar/jogos/{$primeiro}/sumula");
        $this->command->newLine();
    }
}
