<?php

namespace App\Policies;

use App\Models\CotacaoMapa;
use App\Models\User;

/**
 * Quem pode o quê no mapa de cotação.
 *
 * TRÊS PERGUNTAS SE CRUZAM, e nenhuma delas sozinha decide:
 *
 * 1. **A pessoa é da Contabilidade?** O módulo é do setor. É a porta, e vale
 *    para todas as ações, inclusive só olhar. Vem do Gate `acessar-cotacao`
 *    (vínculo com o setor, em qualquer papel) — e, como nos demais recursos de
 *    setor deste projeto, **a role `admin` não abre esta porta**. Quem
 *    administra o sistema não cota compra por consequência disso.
 *
 * 2. **Tem a permissão da ação?** Dentro do setor, as permissões `cotacao.*`
 *    separam ver, montar, cotar, decidir e exportar — quem monta o mapa nem
 *    sempre é quem liga para os fornecedores, e decidir de quem comprar não é a
 *    mesma coisa que anotar o preço que o fornecedor falou.
 *
 * 3. **O mapa ainda aceita alteração?** Um mapa FECHADO é somente leitura para
 *    todo mundo — é ele que sustenta a decisão de compra, e um preço corrigido
 *    depois do fechamento, sem trilha, transforma o documento em rascunho.
 *
 * Reabrir é a exceção da terceira, e por isso tem permissão própria: é a ação
 * que devolve o mapa à edição, e ela fica registrada no log.
 */
class CotacaoMapaPolicy
{
    /**
     * A porta do módulo. Toda ação passa por aqui antes de olhar a permissão.
     */
    private function doSetor(User $user): bool
    {
        return $user->can('acessar-cotacao');
    }

    public function viewAny(User $user): bool
    {
        return $this->doSetor($user) && $user->can('cotacao.visualizar');
    }

    public function view(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $user->can('cotacao.visualizar');
    }

    public function create(User $user): bool
    {
        return $this->doSetor($user) && $user->can('cotacao.criar');
    }

    /**
     * Editar o cabeçalho do mapa, os itens e as colunas de fornecedor.
     *
     * Inclui acrescentar fornecedor à cotação — o comprador pode chamar quem
     * quiser, inclusive quem não está no cadastro do Questor.
     */
    public function update(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel() && $user->can('cotacao.criar');
    }

    /**
     * Digitar preço na grade.
     */
    public function editarPrecos(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel() && $user->can('cotacao.editar_precos');
    }

    /**
     * Escolher de quem comprar cada item. É a decisão, não o dado — por isso
     * não vem junto com a digitação de preço.
     */
    public function definirVencedor(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel() && $user->can('cotacao.definir_vencedor');
    }

    public function exportar(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $user->can('cotacao.exportar');
    }

    /**
     * Fechar encerra a cotação: a partir daí o mapa é o documento da compra.
     */
    public function fechar(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel() && $user->can('cotacao.definir_vencedor');
    }

    /**
     * Reabrir um mapa fechado. Permissão própria e log obrigatório.
     */
    public function reabrir(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->fechado() && $user->can('cotacao.reabrir');
    }

    public function delete(User $user, CotacaoMapa $mapa): bool
    {
        return $this->doSetor($user) && $mapa->editavel() && $user->can('cotacao.criar');
    }
}
