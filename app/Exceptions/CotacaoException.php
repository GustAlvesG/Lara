<?php

namespace App\Exceptions;

use App\Models\CotacaoMapa;
use Exception;

/**
 * Falha de regra do módulo de mapa de cotação.
 *
 * Separada de {@see QuestorException} de propósito: aquela quer dizer "o ERP
 * não respondeu", esta quer dizer "o pedido não faz sentido" (solicitação
 * inexistente, mapa já aberto, mapa fechado). São mensagens diferentes para o
 * comprador e caminhos diferentes na tela.
 */
class CotacaoException extends Exception
{
    /**
     * O mapa envolvido, quando a falha é sobre um mapa que já existe — a tela
     * usa isto para oferecer "abrir o existente" em vez de só recusar.
     */
    public ?CotacaoMapa $mapa = null;

    public static function solicitacaoNaoEncontrada(int $codigo): self
    {
        return new self("Solicitação {$codigo} não encontrada no Questor.");
    }

    public static function solicitacaoSemItens(int $codigo): self
    {
        return new self(
            "A solicitação {$codigo} não tem itens. Não há o que cotar."
        );
    }

    public static function mapaJaExiste(CotacaoMapa $mapa): self
    {
        $e = new self(sprintf(
            'Já existe um mapa (#%d, %s) para a solicitação %d.',
            $mapa->id,
            mb_strtolower($mapa->statusLabel()),
            $mapa->questor_solicitacao
        ));

        $e->mapa = $mapa;

        return $e;
    }

    public static function mapaSomenteLeitura(CotacaoMapa $mapa): self
    {
        return new self(sprintf(
            'O mapa #%d está %s e não aceita alterações.',
            $mapa->id,
            mb_strtolower($mapa->statusLabel())
        ));
    }

    public static function limiteDeFornecedores(int $limite): self
    {
        return new self(
            "O mapa já tem {$limite} colunas de fornecedor, que é o limite configurado. "
            . 'Remova uma coluna antes de acrescentar outra.'
        );
    }
}
