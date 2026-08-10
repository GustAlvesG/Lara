<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Acompanhamento do trâmite — a tela do setor **Comercial**.
 *
 * Quem registra o contrato é quem o freelancer procura para saber "e o meu
 * pagamento?". Até aqui, responder isso exigia abrir a aba de Lotes (que só o
 * coordenador vê), a de Aprovação (só a Gerência) e a de Financeiro (só a
 * Contabilidade) — três telas que o Comercial não alcança, cada uma contando um
 * pedaço. Esta reúne as quatro etapas num eixo só: assinaturas, gerência,
 * diretoria e pagamento.
 *
 * **Só leitura.** Nenhuma ação, nenhum botão que mude estado: aprovar continua
 * sendo da Gerência, e pagar, do Financeiro. O acesso é vínculo com o setor
 * Comercial em qualquer papel (Gate `track-freelancer-batches`) — inclusive de
 * quem não tem `manage freelancers`, e por isso a rota fica fora daquele grupo.
 */
class TrackingController extends Controller
{
    /** Períodos oferecidos no filtro, em dias. `null` = tudo. */
    private const PERIODS = [
        '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias',
        '180' => 'Últimos 6 meses',
        'tudo' => 'Todo o histórico',
    ];

    private const DEFAULT_PERIOD = '90';

    /** Teto das listagens: a tela é de acompanhamento, não de auditoria. */
    private const BATCH_LIMIT = 60;
    private const LOOSE_LIMIT = 120;

    public function index(Request $request)
    {
        $period = array_key_exists((string) $request->query('periodo'), self::PERIODS)
            ? (string) $request->query('periodo')
            : self::DEFAULT_PERIOD;

        $since = $period === 'tudo' ? null : Carbon::now()->subDays((int) $period)->startOfDay();

        return view('freelancer.services.tracking', [
            'periods' => self::PERIODS,
            'period' => $period,
            'summary' => $this->summary($since),
            'batches' => $this->batches($since),
            'loose' => $this->looseServices($since),
            'looseLimit' => self::LOOSE_LIMIT,
            'batchLimit' => self::BATCH_LIMIT,
        ]);
    }

    /**
     * Os contadores do topo — um por etapa da fila, com o valor parado nela.
     *
     * Não são filtrados por período, com uma exceção: o que já foi PAGO. Os
     * demais são filas abertas, e uma fila não fica menos aberta por ser
     * antiga — esconder o contrato de março que ninguém aprovou seria esconder
     * exatamente o que a tela existe para achar.
     */
    private function summary(?Carbon $since): array
    {
        $etapas = [
            'awaiting_signatures' => FreelancerService::awaitingSignature(),
            'awaiting_release' => FreelancerService::awaitingRelease(),
            'awaiting_batch' => FreelancerService::availableForBatch(),
            'awaiting_manager' => FreelancerService::awaitingManagerReview(),
            'awaiting_director' => FreelancerService::awaitingDirectorReview(),
            'awaiting_payment' => FreelancerService::awaitingPayment(),
        ];

        $pagos = FreelancerService::paidServices();

        if ($since !== null) {
            $pagos->where('paid_at', '>=', $since);
        }

        $etapas['paid'] = $pagos;

        return collect($etapas)
            ->map(fn($query, $stage) => [
                'stage' => $stage,
                'label' => FreelancerService::TRACKING_STAGES[$stage],
                'count' => (clone $query)->count(),
                'total' => (float) (clone $query)->sum('price'),
            ])
            ->all();
    }

    /**
     * Lotes com os contratos dentro. Um lote ainda em trâmite entra sempre,
     * mesmo velho — é o que trava a fila; os já encerrados obedecem ao período.
     */
    private function batches(?Carbon $since)
    {
        $abertos = [
            FreelancerServiceBatch::STATUS_DRAFT,
            FreelancerServiceBatch::STATUS_SENT,
            FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
        ];

        $batches = FreelancerServiceBatch::query()
            ->when($since, fn($q) => $q->where(fn($sub) => $sub
                ->whereIn('status', $abertos)
                ->orWhere('created_at', '>=', $since)))
            ->withCount([
                'services',
                // Os mesmos agregados do Financeiro, com a mesma definição:
                // é deles que sai "3 de 8 pagos" e o estado do lote.
                'services as payable_count' => fn($q) => $q->awaitingFinance(),
                'services as paid_count' => fn($q) => $q->awaitingFinance()->where('paid', true),
            ])
            ->withSum('services', 'price')
            ->with([
                'createdBy',
                'reviewedBy',
                'directorDecidedBy',
                'services' => fn($q) => $q->orderBy('start_date')->orderBy('start_time'),
                'services.freelancer:id,name,cpf',
                'services.functionFreelancer',
                // Sem ela, `hasPixInProgress()` responde false por falta da
                // relação e um Pix em andamento apareceria como "a pagar".
                'services.latestPixPayment',
            ])
            ->orderByDesc('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        // O contrato precisa do próprio lote para saber em que etapa está, e ele
        // já está aqui na mão: sem isto, cada linha da tabela faria uma consulta
        // para reler o lote que a acabou de trazer.
        $batches->each(fn(FreelancerServiceBatch $batch) => $batch->services
            ->each(fn(FreelancerService $service) => $service->setRelation('batch', $batch)));

        return $batches;
    }

    /**
     * Contratos que ainda não estão num lote em trâmite: os que esperam
     * assinatura e os que já foram assinados e aguardam o coordenador montar o
     * lote. São o começo da fila — e o ponto em que um contrato costuma ficar
     * esquecido, porque ainda não é problema de ninguém mais.
     */
    private function looseServices(?Carbon $since)
    {
        $relations = ['freelancer:id,name,cpf', 'functionFreelancer', 'batch'];

        $aguardandoAssinatura = FreelancerService::awaitingSignature()
            ->when($since, fn($q) => $q->where('start_date', '>=', $since))
            ->with($relations)
            ->orderByDesc('start_date')
            ->limit(self::LOOSE_LIMIT)
            ->get();

        // Turnos de hoje e de ontem à noite, esperando as 08h para irem à
        // coordenação. Sempre listados, mesmo com período curto: são os mais
        // recentes de todos.
        $aguardandoLiberacao = FreelancerService::awaitingRelease()
            ->with($relations)
            ->orderByDesc('start_date')
            ->limit(self::LOOSE_LIMIT)
            ->get();

        $aguardandoLote = FreelancerService::availableForBatch()
            ->when($since, fn($q) => $q->where('start_date', '>=', $since))
            ->with($relations)
            ->orderByDesc('start_date')
            ->limit(self::LOOSE_LIMIT)
            ->get();

        // `availableForBatch` traz também o que a gerência ou a diretoria
        // recusou e voltou para a fila — o rótulo de cada linha diz qual é qual.
        return $aguardandoAssinatura
            ->concat($aguardandoLiberacao)
            ->concat($aguardandoLote)
            ->sortByDesc(fn(FreelancerService $s) => $s->start_date?->toDateString())
            ->values();
    }
}
