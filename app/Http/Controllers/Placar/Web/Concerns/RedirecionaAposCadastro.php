<?php

namespace App\Http\Controllers\Placar\Web\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Para onde ir depois de cadastrar. O formulário de criação tem dois botões
 * (ver placar/partials/botoes-cadastro):
 *
 * - "Cadastrar" (`acao=salvar`, o padrão — é o que o Enter aciona) volta
 *   para a lista. Antes abria a ficha do registro, que é um formulário de
 *   edição: quem acabou de cadastrar caía editando o que tinha digitado.
 * - "Cadastrar e criar outro" (`acao=continuar`) reabre o formulário vazio,
 *   mas com os campos de contexto ($manter) já preenchidos — quem cadastra
 *   vários times da mesma equipe, ou a rodada inteira de jogos, não repete
 *   equipe, modalidade, categoria e data a cada um.
 *
 * $link vira um atalho no aviso de sucesso (ver partials/alerts) para o
 * próximo passo natural: montar o elenco, a escalação, etc.
 */
trait RedirecionaAposCadastro
{
    /**
     * @param  string  $recurso  sufixo das rotas: "times" → placar.times.index / .create
     * @param  array<int, string>  $manter  campos que voltam preenchidos no "criar outro"
     * @param  array{url: string, label: string}|null  $link
     */
    protected function aposCadastrar(
        Request $request,
        string $recurso,
        string $mensagem,
        array $manter = [],
        ?array $link = null,
    ): RedirectResponse {
        $continuar = $request->input('acao') === 'continuar';

        $resposta = redirect()
            ->route($continuar ? "placar.{$recurso}.create" : "placar.{$recurso}.index")
            ->with('success', $mensagem);

        // Só os campos listados: withInput() sem argumento devolveria o
        // request inteiro, e o nome do registro recém-criado voltaria
        // preenchido no formulário.
        if ($continuar && $manter !== []) {
            $resposta->withInput($request->only($manter));
        }

        if ($link) {
            $resposta->with('success_link', $link);
        }

        return $resposta;
    }
}
