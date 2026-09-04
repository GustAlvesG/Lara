<?php

namespace App\Policies;

use App\Models\CotacaoMapa;
use App\Models\User;

/**
 * Quem pode o quê no mapa de cotação.
 *
 * **O MÓDULO É DO SETOR CONTABILIDADE.** Estar no setor — em QUALQUER papel,
 * colaborador ou coordenador — dá acesso à aba e ao trabalho todo: ver, montar
 * o mapa, digitar preço, escolher vencedor, fechar e exportar.
 *
 * Não há permissão do Spatie no caminho, e isso é deliberado. É o mesmo arranjo
 * do financeiro dos freelancers (`manage-freelancer-payments`): quando o acesso
 * é atribuição de setor, exigir também uma permissão cria uma segunda porta que
 * ninguém lembra de abrir — e o efeito prático é o funcionário entrar no setor,
 * continuar levando 403 e ninguém saber por quê.
 *
 * Duas consequências que valem ser ditas em voz alta:
 *
 *   - **A role `admin` não abre nada aqui.** Quem administra o sistema não cota
 *     compra por consequência disso; entra no setor quem de fato cota.
 *   - **Tirar alguém do setor corta o acesso na hora.** É uma consulta por
 *     requisição, memorizada só dentro dela.
 *
 * O que NÃO vem com o setor é reabrir um mapa fechado: ver {@see reabrir()}.
 *
 * A terceira pergunta, que se cruza com a do setor em toda ação de escrita, é o
 * ESTADO do mapa: fechado ou cancelado é somente leitura para todo mundo — é
 * ele que sustenta a decisão de compra, e um preço corrigido depois do
 * fechamento, sem trilha, transformaria o documento em rascunho.
 */
class CotacaoMapaPolicy
{
    /**
     * A porta do módulo: vínculo com a Contabilidade, em qualquer papel.
     *
     * Passa pelo Gate `acessar-cotacao` em vez de chamar o model direto porque
     * o menu faz a mesma pergunta em toda tela, e `can()` funciona com o
     * usuário montado à mão dos testes.
     */
    private function doSetor(User $user): bool
    {
        return $user->can('acessar-cotacao');
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
