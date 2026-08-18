<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Models\Placar\Elenco;
use App\Models\Placar\Jogador;
use App\Models\Placar\Time;
use Illuminate\Http\Request;

/**
 * Vínculo jogador↔time↔temporada, gerenciado a partir da ficha do time
 * (TimeController@show) — não tem tela própria.
 */
class ElencoController extends Controller
{
    /** POST /placar/times/{time}/elenco — adiciona um jogador ao elenco da temporada corrente. */
    public function store(Request $request, Time $time)
    {
        $data = $request->validate([
            'jogador_id' => ['required', 'integer', 'exists:jogadores,id'],
            'numero' => ['nullable', 'string', 'max:10'],
            'posicao' => ['nullable', 'string', 'max:255'],
        ]);

        $jogador = Jogador::findOrFail($data['jogador_id']);

        // Jogador é de uma única equipe e uma única modalidade: só entra em
        // time que case com as duas.
        if (!$jogador->podeJogarPor($time)) {
            return back()->with('error', sprintf(
                '%s é da equipe %s / %s e não pode entrar num time de %s / %s.',
                $jogador->nomeExibicaoResolvido(),
                $jogador->equipe?->nome ?? '—',
                $jogador->modalidade?->nome ?? '—',
                $time->equipe->nome,
                $time->modalidade->nome,
            ));
        }

        $temporada = now()->year;

        $elenco = Elenco::firstOrNew([
            'time_id' => $time->id,
            'jogador_id' => $data['jogador_id'],
            'temporada' => $temporada,
        ]);

        if ($elenco->exists && $elenco->ativo) {
            return back()->with('error', 'Este jogador já está no elenco desta temporada.');
        }

        $elenco->fill([
            'numero' => $data['numero'] ?? null,
            'posicao' => $data['posicao'] ?? null,
            'ativo' => true,
        ])->save();

        return back()->with('success', 'Jogador adicionado ao elenco.');
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
