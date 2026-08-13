<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use App\Models\CostCenterApprover;
use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseOrderApprovalStep;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O fluxo de três níveis: Contabilidade → Gerência → Diretoria.
 *
 * O Questor só é tocado quando o terceiro nível fecha, e é
 * {@see QuestorAuthorizationWriter} quem toca. Aqui mora o processo: quem
 * decide, em que ordem, o que ainda falta e quando acabou.
 *
 * Três regras que valem para o fluxo inteiro:
 *
 *  - **Cada nível é uma decisão própria.** A mesma pessoa pode ocupar os três
 *    cargos e ainda assim precisa decidir três vezes. Aprovar um nível nunca
 *    aprova o seguinte, nem por atalho nem por acúmulo de cargo.
 *  - **Qualquer reprovação encerra.** Compra reprovada não melhora com mais
 *    votos; os passos que sobraram viram `skipped` e o processo fecha.
 *  - **O nível 3 é escolhido pela Gerência.** A relação centro de custo →
 *    diretor entra como sugestão preenchida; o gerente confirma ou troca, e a
 *    origem de cada escolha fica registrada.
 */
class PurchaseOrderApprovalService
{
    public function __construct(
        private readonly QuestorPurchaseOrders $orders,
        private readonly QuestorCostCenters $costCenters,
        private readonly QuestorAuthorizationWriter $writer,
    ) {
    }

    /**
     * O processo aberto da ordem, ou `null` se ela ainda não entrou no fluxo.
     */
    public function current(int $cdOrdemCompra): ?PurchaseOrderApproval
    {
        return PurchaseOrderApproval::openFor($cdOrdemCompra);
    }

    /**
     * Os diretores sugeridos para uma ordem, a partir dos centros de custo dos
     * itens dela. É o que a tela do gerente carrega marcado.
     *
     * @return Collection<int, User>
     *
     * @throws QuestorException
     */
    public function suggestedDirectors(int $cdOrdemCompra): Collection
    {
        return CostCenterApprover::suggestedFor(
            $this->costCenters->forOrder($cdOrdemCompra)['codigos']
        );
    }

    /**
     * Decide o nível 1 (Contabilidade). Cria o processo, porque é aqui que a
     * ordem entra no fluxo — não há botão de "iniciar" separado.
     *
     * @throws QuestorException
     */
    public function decideAccounting(int $cdOrdemCompra, User $user, bool $approve, ?string $note, ?string $ip = null): PurchaseOrderApproval
    {
        if (!$user->isAccountingCoordinator()) {
            throw new QuestorException('Só o coordenador da Contabilidade decide o primeiro nível.');
        }

        $aberto = $this->current($cdOrdemCompra);

        if ($aberto !== null) {
            throw new QuestorException(
                "Esta ordem já está em aprovação, no nível {$aberto->current_level} ({$aberto->currentLevelLabel()})."
            );
        }

        $ordem = $this->orders->find($cdOrdemCompra);
        $centros = $this->costCenters->forOrder($cdOrdemCompra);

        $approval = DB::transaction(function () use ($ordem, $centros, $user) {
            $approval = PurchaseOrderApproval::create([
                'cd_ordem_compra' => (int) $ordem->CD_ORDEM_COMPRA,
                'cd_filial' => $ordem->CD_FILIAL,
                'vl_total' => $ordem->VL_TOTAL,
                'nr_itens' => $ordem->NR_ITENS,
                'cost_centers' => $centros['codigos'],
                'sem_centro_custo' => $centros['sem_centro_custo'],
                'status' => PurchaseOrderApproval::STATUS_OPEN,
                'current_level' => PurchaseOrderApproval::LEVEL_ACCOUNTING,
                'started_by' => $user->id,
            ]);

            $approval->steps()->create([
                'level' => PurchaseOrderApproval::LEVEL_ACCOUNTING,
                'role' => PurchaseOrderApprovalStep::ROLE_ACCOUNTING,
            ]);

            return $approval->load('steps');
        });

        return $this->apply($approval, $user, $approve, $note, $ip);
    }

    /**
     * Decide o nível 2 (Gerência) e, aprovando, define quem são os diretores do
     * nível 3.
     *
     * `$directorIds` é obrigatório na aprovação: sem diretor escolhido, o nível
     * 3 nasceria vazio e a ordem ficaria aberta sem ninguém para decidi-la — que
     * é exatamente o que acontece com as ordens sem centro de custo se ninguém
     * escolher por elas.
     *
     * @param  array<int, int>  $directorIds
     *
     * @throws QuestorException
     */
    public function decideManagement(
        int $cdOrdemCompra,
        User $user,
        bool $approve,
        array $directorIds,
        ?string $note,
        ?string $ip = null,
    ): PurchaseOrderApproval {
        $approval = $this->requireOpen($cdOrdemCompra);

        if ($approval->current_level !== PurchaseOrderApproval::LEVEL_MANAGEMENT) {
            throw new QuestorException(
                "Esta ordem está no nível {$approval->currentLevelLabel()}, não na Gerência."
            );
        }

        if (!$user->isManagementCoordinator()) {
            throw new QuestorException('Só o coordenador da Gerência decide o segundo nível.');
        }

        if ($approve) {
            $this->prepareDirectors($approval, $directorIds, $cdOrdemCompra);
        }

        return $this->apply($approval, $user, $approve, $note, $ip);
    }

    /**
     * Decide o nível 3 (Diretoria). Cada diretor decide o próprio passo.
     *
     * @throws QuestorException
     */
    public function decideDirector(int $cdOrdemCompra, User $user, bool $approve, ?string $note, ?string $ip = null): PurchaseOrderApproval
    {
        $approval = $this->requireOpen($cdOrdemCompra);

        if ($approval->current_level !== PurchaseOrderApproval::LEVEL_DIRECTORS) {
            throw new QuestorException(
                "Esta ordem está no nível {$approval->currentLevelLabel()} e ainda não chegou à Diretoria."
            );
        }

        return $this->apply($approval, $user, $approve, $note, $ip);
    }

    /**
     * Cria os passos do nível 3 a partir da escolha do gerente.
     *
     * A validação é dupla de propósito: o formulário manda ids, e ids não são
     * prova de nada. Só entra quem está no setor Diretoria.
     *
     * @param  array<int, int>  $directorIds
     *
     * @throws QuestorException
     */
    private function prepareDirectors(PurchaseOrderApproval $approval, array $directorIds, int $cdOrdemCompra): void
    {
        $escolhidos = collect($directorIds)->map(fn($id) => (int) $id)->unique()->values();

        if ($escolhidos->isEmpty()) {
            throw new QuestorException(
                'Escolha ao menos um diretor para o último nível — sem isso a ordem ficaria aprovada pela '
                . 'Gerência e parada, sem ninguém para decidi-la.'
            );
        }

        $diretores = User::directors()->whereIn('id', $escolhidos->all())->get();

        if ($diretores->count() !== $escolhidos->count()) {
            throw new QuestorException('Só usuários do setor Diretoria podem aprovar o último nível.');
        }

        // Quem veio da relação de centro de custo e quem o gerente acrescentou.
        // A ordem tem os dois casos misturados quando parte dos itens tem CC e
        // parte não.
        $sugeridos = $this->suggestedDirectors($cdOrdemCompra)->pluck('id');

        DB::transaction(function () use ($approval, $diretores, $sugeridos) {
            foreach ($diretores as $diretor) {
                $approval->steps()->create([
                    'level' => PurchaseOrderApproval::LEVEL_DIRECTORS,
                    'user_id' => $diretor->id,
                    'source' => $sugeridos->contains($diretor->id)
                        ? PurchaseOrderApprovalStep::SOURCE_SUGGESTED
                        : PurchaseOrderApprovalStep::SOURCE_MANUAL,
                ]);
            }

            $approval->load('steps');
        });
    }

    /**
     * Grava a decisão no passo do usuário e faz o processo avançar.
     *
     * @throws QuestorException
     */
    private function apply(
        PurchaseOrderApproval $approval,
        User $user,
        bool $approve,
        ?string $note,
        ?string $ip,
    ): PurchaseOrderApproval {
        $step = $approval->stepFor($user);

        if ($step === null) {
            throw new QuestorException(
                'Não há decisão pendente sua nesta ordem. '
                . 'Ou não é a sua vez, ou você já decidiu este nível.'
            );
        }

        DB::transaction(function () use ($step, $user, $approve, $note, $ip) {
            $step->forceFill([
                'decision' => $approve
                    ? PurchaseOrderApprovalStep::APPROVED
                    : PurchaseOrderApprovalStep::REJECTED,
                'decided_by' => $user->id,
                'decided_by_name' => $user->name,
                'decided_at' => now(),
                'decided_ip' => $ip,
                'note' => $note,
            ])->save();
        });

        $approval->load('steps');

        Log::info('Questor: decisão de aprovação', [
            'approval_id' => $approval->id,
            'cd_ordem_compra' => $approval->cd_ordem_compra,
            'nivel' => $step->level,
            'decisao' => $step->decision,
            'user_id' => $user->id,
        ]);

        return $approve
            ? $this->advance($approval)
            : $this->close($approval, PurchaseOrderApproval::STATUS_REJECTED);
    }

    /**
     * Depois de uma aprovação: o nível acabou? Se sim, sobe — e se era o
     * último, grava no Questor.
     *
     * @throws QuestorException
     */
    private function advance(PurchaseOrderApproval $approval): PurchaseOrderApproval
    {
        if ($approval->pendingSteps()->isNotEmpty() && $this->requiresEveryone()) {
            return $approval; // ainda faltam diretores neste nível
        }

        if ($approval->current_level < PurchaseOrderApproval::LEVEL_DIRECTORS) {
            // Os passos do próximo nível: o 2 é cargo e nasce aqui; o 3 nasce
            // na mão do gerente, ao aprovar o 2.
            $proximo = $approval->current_level + 1;

            if ($proximo === PurchaseOrderApproval::LEVEL_MANAGEMENT) {
                $approval->steps()->create([
                    'level' => PurchaseOrderApproval::LEVEL_MANAGEMENT,
                    'role' => PurchaseOrderApprovalStep::ROLE_MANAGEMENT,
                ]);
            }

            $approval->forceFill(['current_level' => $proximo])->save();

            return $approval->load('steps');
        }

        // Nível 3 fechado com aprovação: é a hora do Questor.
        return $this->writeToQuestor($approval);
    }

    /**
     * A gravação final.
     *
     * Antes de gravar, confere se a ordem ainda é a mesma que foi aprovada: um
     * valor ou uma contagem de itens diferente significa que alguém editou a
     * ordem no Questor durante o trâmite, e o que a Diretoria aprovou não é o
     * que seria autorizado.
     *
     * @throws QuestorException
     */
    private function writeToQuestor(PurchaseOrderApproval $approval): PurchaseOrderApproval
    {
        $ordem = $this->orders->find($approval->cd_ordem_compra);

        $mudou = (float) $ordem->VL_TOTAL !== (float) $approval->vl_total
            || (int) $ordem->NR_ITENS !== (int) $approval->nr_itens;

        if ($mudou) {
            throw new QuestorException(sprintf(
                'A ordem mudou no Questor durante a aprovação (aprovada com %s e %d itens; hoje está com %s e %d). '
                . 'A gravação foi interrompida — confira a ordem e reinicie o fluxo.',
                'R$ ' . number_format((float) $approval->vl_total, 2, ',', '.'),
                (int) $approval->nr_itens,
                'R$ ' . number_format((float) $ordem->VL_TOTAL, 2, ',', '.'),
                (int) $ordem->NR_ITENS,
            ));
        }

        $resultado = $this->writer->approve(
            $approval->cd_ordem_compra,
            observacao: $this->questorNote($approval),
        );

        $approval->forceFill([
            'questor_decision_id' => $resultado['decision_id'] ?? null,
        ])->save();

        return $this->close($approval, PurchaseOrderApproval::STATUS_APPROVED);
    }

    /**
     * O texto que vai para `DS_OBS` no Questor.
     *
     * O identificador vem na frente porque a coluna é `varchar(5000)` e o corte
     * come o fim: se sobrar pouco espaço, o que fica é justamente o que permite
     * achar o processo na Lara.
     */
    private function questorNote(PurchaseOrderApproval $approval): string
    {
        $texto = sprintf(
            '[LARA #%d] Autorizado pelo Lara em %s. %s.',
            $approval->id,
            now()->format('d/m/Y H:i'),
            $approval->approversSummary(),
        );

        return PHP_EOL . Str::limit($texto, 500, '');
    }

    /** Encerra o processo, marcando o que sobrou como não decidido. */
    private function close(PurchaseOrderApproval $approval, string $status): PurchaseOrderApproval
    {
        DB::transaction(function () use ($approval, $status) {
            $approval->steps()
                ->where('decision', PurchaseOrderApprovalStep::PENDING)
                ->update(['decision' => PurchaseOrderApprovalStep::SKIPPED]);

            $approval->forceFill([
                'status' => $status,
                'closed_at' => now(),
            ])->save();
        });

        return $approval->load('steps');
    }

    /**
     * No nível 3, todos os diretores escolhidos precisam aprovar, ou basta um?
     *
     * Padrão `todos`: o gerente escolhe a dedo quem decide aquela ordem, então
     * escolher três pessoas se lê como "estas três precisam aprovar". Vira
     * `qualquer` pelo .env quando a operação preferir velocidade.
     */
    private function requiresEveryone(): bool
    {
        return config('questor.aprovacao.quorum_diretoria', 'todos') === 'todos';
    }

    /** @throws QuestorException */
    private function requireOpen(int $cdOrdemCompra): PurchaseOrderApproval
    {
        $approval = $this->current($cdOrdemCompra);

        if ($approval === null) {
            throw new QuestorException('Esta ordem ainda não entrou no fluxo de aprovação.');
        }

        return $approval;
    }
}
