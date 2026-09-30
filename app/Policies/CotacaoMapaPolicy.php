<?php

namespace App\Policies;

use App\Models\CotacaoMapa;
use App\Models\User;

/**
 * Quem pode o quê no mapa de cotação.
 *
 * **A porta é a permissão `compras`** (setor Contabilidade — o Financeiro —
 * na matriz inicial, ou quem tiver acesso total). Tê-la dá o trabalho todo:
 * ver, montar o mapa, digitar preço, escolher vencedor, fechar e exportar.
 *
 * O que NÃO vem com a permissão é reabrir um mapa fechado: ver {@see reabrir()}.
 * Isso é cargo (coordenador da Contabilidade), e o acesso total não o dá.
 *
 * A outra pergunta, que se cruza com a da permissão em toda ação de escrita, é
 * o ESTADO do mapa: fechado ou cancelado é somente leitura para todo mundo —
 * inclusive para o acesso total. É ele que sustenta a decisão de compra, e um
 * preço corrigido depois do fechamento, sem trilha, transformaria o documento
 * em rascunho. Por isso esta policy não é atravessada pelo Gate::before: as
 * abilities dela não estão no catálogo.
 */
class CotacaoMapaPolicy
{
    /**
     * A porta do módulo. Passa por `can()` e não pelo model direto porque o
     * menu faz a mesma pergunta em toda tela, e `can()` funciona com o
     * usuário montado à mão dos testes.
     */
    private function doSetor(User $user): bool
    {
        return $user->can('compras');
    }

    public function viewAny(User $user): bool
    {
        return $this->doSetor($user);
    }

    public function view(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user);
    }

    public function create(User $user): bool
    {
        return $this->doSetor($user);
    }

    /**
     * Editar o cabeçalho, os itens e as colunas de fornecedor — inclusive
     * acrescentar à cotação quem não está no cadastro do Questor.
     */
    public function update(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel();
    }

    /**
     * Digitar preço na grade.
     */
    public function editarPrecos(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel();
    }

    /**
     * Escolher de quem comprar cada item.
     */
    public function definirVencedor(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel();
    }

    public function exportar(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user);
    }

    /**
     * Fechar encerra a cotação: a partir daí o mapa é o documento da compra.
     */
    public function fechar(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel();
    }

    /**
     * Reabrir um mapa fechado — a única ação que o setor não dá por si.
     *
     * É do COORDENADOR da Contabilidade, e não de qualquer membro: reabrir
     * devolve à edição um documento que já fundamentou uma compra, possivelmente
     * já assinado e arquivado. Fica registrado no log de qualquer forma.
     *
     * Coordenação em vez de permissão do Spatie pelo mesmo motivo do resto da
     * policy: é um cargo que o painel de setores já mantém, e não uma caixinha
     * que alguém precisa lembrar de marcar. Mesmo critério do primeiro nível da
     * aprovação de ordem de compra ({@see User::isAccountingCoordinator()}).
     */
    public function reabrir(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user)
            && $mapa->fechado()
            && $user->isAccountingCoordinator();
    }

    public function delete(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel();
    }
}
