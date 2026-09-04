<?php

namespace App\Http\Controllers\Questor;

use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Models\CostCenterApprover;
use App\Models\User;
use App\Services\Questor\QuestorCostCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Relação centro de custo → diretores.
 *
 * A tela define **quem vem sugerido** quando a Gerência aprova uma ordem — não
 * quem decide. A palavra final é do gerente, que pode trocar os diretores de
 * qualquer ordem e precisa escolher do zero as que não têm centro de custo.
 *
 * Editar aqui muda quem costuma aprovar o quê, então é uma tela de alçada
 * disfarçada de cadastro. A permissão exigida é a mesma da fila de ordens.
 */
class CostCenterApproverController extends Controller
{
    public function __construct(private readonly QuestorCostCenters $costCenters)
    {
    }

    public function index()
    {
        $dados = [
            'centros' => new Collection,
            'diretores' => User::directors()->get(),
            'relacao' => CostCenterApprover::mapByCostCenter(),
            'erro' => null,
        ];

        try {
            $dados['centros'] = $this->costCenters->inUse();
        } catch (QuestorException $e) {
            $dados['erro'] = $e->getMessage();
        }

        return view('questor.cost-centers.index', $dados);
    }

    /**
     * Salva os diretores de um centro de custo.
     *
     * Lista vazia é entrada válida: significa "este centro de custo não tem
     * diretor definido", e a tela mostra isso em destaque. Recusar o vazio
     * obrigaria a inventar um responsável só para o formulário passar.
     */
    public function update(Request $request, int $centroCusto)
    {
        $dados = $request->validate([
            'diretores' => ['array'],
            // `exists` sozinho aceitaria qualquer usuário. O universo aqui é o
            // setor Diretoria, e a checagem não pode depender só do <select>.
            'diretores.*' => ['integer', 'distinct'],
        ]);

        $permitidos = User::directors()->pluck('id');
        $escolhidos = collect($dados['diretores'] ?? [])->map(fn($id) => (int) $id);

        if ($escolhidos->diff($permitidos)->isNotEmpty()) {
            return back()->with('error', 'Só é possível vincular usuários do setor Diretoria.');
        }

        CostCenterApprover::sync($centroCusto, $escolhidos->all());

        return back()->with(
            'success',
            $escolhidos->isEmpty()
                ? "Centro de custo {$centroCusto} ficou sem diretor vinculado."
                : "Diretores do centro de custo {$centroCusto} atualizados."
        );
    }
}
