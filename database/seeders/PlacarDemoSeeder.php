<?php

namespace Database\Seeders;

use App\Models\Placar\Competicao;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use Illuminate\Database\Seeder;

/**
 * Massa de demonstração do Placar Clube: duas equipes, cada uma com time de
 * futsal adulto, basquete adulto e basquete Sub-15 (~10 jogadores por time),
 * uma competição e três jogos agendados — um por modalidade — para dar o que
 * testar no Node e nas telas web sem precisar cadastrar nada à mão.
 *
 *   php artisan db:seed --class=PlacarDemoSeeder
 *
 * Idempotente: usa firstOrCreate/updateOrCreate em tudo, então rodar de novo
 * não duplica — só garante que a massa exista.
 */
class PlacarDemoSeeder extends Seeder
{
    public function run(): void
    {
        $futsal = Modalidade::where('slug', Modalidade::FUTSAL)->first();
        $basquete = Modalidade::where('slug', Modalidade::BASQUETE)->first();

        if (!$futsal || !$basquete) {
            $this->command->error('Modalidades não encontradas — rode o ModalidadeSeeder antes.');

            return;
        }

        $equipeCF = $this->criarEquipe('Clube dos Funcionários', 'CF', 'Volta Redonda');
        $equipeVN = $this->criarEquipe('Associação Vila Nova', 'Vila Nova', 'Barra Mansa');

        $competicao = Competicao::firstOrCreate(
            ['nome' => 'Copa Clube 2026', 'modalidade_id' => $futsal->id, 'temporada' => 2026],
            ['ativo' => true],
        );

        // [equipe, modalidade, categoria, prefixo do dorsal] — o prefixo só
        // existe para os nomes dos jogadores não se repetirem entre os times.
        $times = [];
        foreach ([$equipeCF, $equipeVN] as $equipe) {
            $times[] = $this->criarTime($equipe, $futsal, Time::CATEGORIA_PADRAO);
            $times[] = $this->criarTime($equipe, $basquete, Time::CATEGORIA_PADRAO);
            $times[] = $this->criarTime($equipe, $basquete, 'Sub-15');
        }

        foreach ($times as $time) {
            $this->criarElenco($time);
        }

        [$futsalCF, $basqueteCF, $sub15CF, $futsalVN, $basqueteVN, $sub15VN] = $times;

        $jogo1 = $this->criarJogo($futsal, $futsalCF, $futsalVN, $competicao, now()->addDay()->setTime(19, 30), 'Quadra Poliesportiva do Clube');
        $jogo2 = $this->criarJogo($basquete, $basqueteCF, $basqueteVN, null, now()->addDays(2)->setTime(20, 0), 'Ginásio Municipal');
        $jogo3 = $this->criarJogo($basquete, $sub15CF, $sub15VN, null, now()->addDays(3)->setTime(15, 0), 'Ginásio Municipal');

        $this->command->newLine();
        $this->command->info('Massa de demonstração do Placar Clube pronta:');
        $this->command->line("  Equipes: {$equipeCF->nome} (#{$equipeCF->id}) x {$equipeVN->nome} (#{$equipeVN->id})");
        $this->command->line("  Competição: {$competicao->nome} (#{$competicao->id})");
        $this->command->line("  Jogo #{$jogo1->id} — futsal, {$jogo1->data_hora->format('d/m H:i')}");
        $this->command->line("  Jogo #{$jogo2->id} — basquete adulto, {$jogo2->data_hora->format('d/m H:i')}");
        $this->command->line("  Jogo #{$jogo3->id} — basquete Sub-15, {$jogo3->data_hora->format('d/m H:i')}");
    }

    private function criarEquipe(string $nome, string $nomeCurto, string $cidade): Equipe
    {
        return Equipe::firstOrCreate(
            ['nome' => $nome],
            ['nome_curto' => $nomeCurto, 'cidade' => $cidade, 'criado_em_campo' => false, 'ativo' => true],
        );
    }

    private function criarTime(Equipe $equipe, Modalidade $modalidade, string $categoria): Time
    {
        return Time::firstOrCreate(
            ['equipe_id' => $equipe->id, 'modalidade_id' => $modalidade->id, 'categoria' => $categoria],
            ['criado_em_campo' => false, 'ativo' => true],
        );
    }

    /** 10 jogadores por time, vinculados na temporada corrente com número sequencial. */
    private function criarElenco(Time $time): void
    {
        if ($time->elencos()->count() >= 10) {
            return;
        }

        for ($numero = 1; $numero <= 10; $numero++) {
            $nome = fake('pt_BR')->name();

            // Jogador é de uma equipe e uma modalidade só — as do próprio
            // time, que é o recorte da equipe naquela modalidade.
            $jogador = Jogador::firstOrCreate(
                ['nome' => $nome],
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
    }

    private function criarJogo(
        Modalidade $modalidade,
        Time $timeCasa,
        Time $timeFora,
        ?Competicao $competicao,
        $dataHora,
        string $local,
    ): Jogo {
        return Jogo::firstOrCreate(
            ['time_casa_id' => $timeCasa->id, 'time_fora_id' => $timeFora->id, 'data_hora' => $dataHora],
            [
                'modalidade_id' => $modalidade->id,
                'competicao_id' => $competicao?->id,
                'local' => $local,
                'status' => Jogo::STATUS_AGENDADO,
                'criado_em_campo' => false,
            ],
        );
    }
}
