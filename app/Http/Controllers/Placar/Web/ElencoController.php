<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Elenco;
use App\Models\Placar\Jogador;
use App\Models\Placar\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo jogador↔time↔temporada, gerenciado a partir da ficha do time
 * (TimeController@show) — não tem tela própria.
 */
class ElencoController extends Controller
{
    /**
     * POST /placar/times/{time}/elenco — adiciona ao elenco da temporada
     * corrente todos os jogadores marcados de uma vez.
     *
     * Formato `jogadores[{id}][selecionado|numero|posicao]`, o mesmo já
     * usado pela tela de escalação — montar um elenco é marcar vários e
     * salvar uma vez, não um POST por jogador.
     *
     * Diferente da escalação, aqui a operação **acrescenta**: quem já está
     * no elenco e não veio marcado continua onde está.
     */
    public function store(Request $request, Time $time)
    {
        // `selecionado` precisa estar nas regras mesmo sendo só um marcador:
        // validate() devolve apenas as chaves que têm regra, e sem ela o
        // checkbox seria descartado do array validado.
        $data = $request->validate([
            'jogadores' => ['required', 'array'],
            'jogadores.*.selecionado' => ['nullable'],
            'jogadores.*.numero' => ['nullable', 'string', 'max:10'],
            'jogadores.*.posicao' => ['nullable', 'string', 'max:255'],
        ]);

        $marcados = collect($data['jogadores'])
            ->filter(fn (array $item) => filled($item['selecionado'] ?? null));

        if ($marcados->isEmpty()) {
            return back()->with('warning', 'Nenhum jogador foi marcado.');
        }

        $temporada = now()->year;
        $jogadores = Jogador::whereIn('id', $marcados->keys())->get()->keyBy('id');

        $adicionados = 0;
        $recusados = [];
        $jaEstavam = [];

        DB::transaction(function () use ($marcados, $jogadores, $time, $temporada, &$adicionados, &$recusados, &$jaEstavam) {
            foreach ($marcados as $jogadorId => $item) {
                $jogador = $jogadores->get((int) $jogadorId);

                if (!$jogador) {
                    continue;
                }

                // Jogador é de uma única equipe e uma única modalidade: só
                // entra em time que case com as duas.
                if (!$jogador->podeJogarPor($time)) {
                    $recusados[] = $jogador->nomeExibicaoResolvido();

                    continue;
                }

                $elenco = Elenco::firstOrNew([
                    'time_id' => $time->id,
                    'jogador_id' => $jogador->id,
                    'temporada' => $temporada,
                ]);

                if ($elenco->exists && $elenco->ativo) {
                    $jaEstavam[] = $jogador->nomeExibicaoResolvido();

                    continue;
                }

                $elenco->fill([
                    'numero' => blank($item['numero'] ?? null) ? null : $item['numero'],
                    'posicao' => blank($item['posicao'] ?? null) ? null : $item['posicao'],
                    'ativo' => true,
                ])->save();

                $adicionados++;
            }
        });

        return back()->with(...$this->resumo($adicionados, $recusados, $jaEstavam));
    }

    /**
     * Uma mensagem só para o lote inteiro: quantos entraram e, se algum
     * ficou de fora, por quê — nomeando quem, para não deixar o usuário
     * conferindo a lista para descobrir o que faltou.
     *
     * @return array{0: string, 1: string}
     */
    private function resumo(int $adicionados, array $recusados, array $jaEstavam): array
    {
        $partes = [];

        if ($adicionados > 0) {
            $partes[] = $adicionados === 1
                ? '1 jogador adicionado ao elenco.'
                : "{$adicionados} jogadores adicionados ao elenco.";
        }

        if ($jaEstavam) {
            $partes[] = 'Já estava(m) no elenco: ' . implode(', ', $jaEstavam) . '.';
        }

        if ($recusados) {
            $partes[] = 'Fora da equipe/modalidade deste time: ' . implode(', ', $recusados) . '.';
        }

        $nivel = match (true) {
            $adicionados === 0 => 'error',
            $recusados || $jaEstavam => 'warning',
            default => 'success',
        };

        return [$nivel, implode(' ', $partes)];
    }

    /** DELETE /placar/times/{time}/elenco/{elenco} — desativa o vínculo (não apaga: preserva o histórico de temporadas). */
    public function destroy(Time $time, Elenco $elenco)
    {
        abort_if($elenco->time_id !== $time->id, 404);

        $elenco->update(['ativo' => false]);

        return back()->with('success', 'Jogador removido do elenco.');
    }

    /**
     * POST /placar/times/{time}/elenco/copiar — copia o elenco ativo da
     * temporada anterior para a corrente (mesmo número/posição), pulando
     * quem já está vinculado. Não sobrescreve: só completa.
     */
    public function copiar(Time $time)
    {
        $temporada = now()->year;
        $anterior = $time->elencos()->where('temporada', $temporada - 1)->where('ativo', true)->get();

        if ($anterior->isEmpty()) {
            return back()->with('error', "O time não tem elenco ativo em {$temporada}. Nada para copiar.");
        }

        $jaNaAtual = $time->elencos()->where('temporada', $temporada)->pluck('jogador_id')->flip();

        $copiados = 0;
        foreach ($anterior as $vinculoAnterior) {
            if (isset($jaNaAtual[$vinculoAnterior->jogador_id])) {
                continue;
            }

            Elenco::create([
                'time_id' => $time->id,
                'jogador_id' => $vinculoAnterior->jogador_id,
                'temporada' => $temporada,
                'numero' => $vinculoAnterior->numero,
                'posicao' => $vinculoAnterior->posicao,
                'ativo' => true,
            ]);
            $copiados++;
        }

        return back()->with(
            $copiados > 0 ? 'success' : 'warning',
            $copiados > 0
                ? "{$copiados} jogador(es) copiado(s) da temporada " . ($temporada - 1) . '.'
                : 'Todos os jogadores da temporada anterior já estavam no elenco atual.',
        );
    }
}
