<?php

namespace App\Services\Signature\MinorTerms;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureKioskDevice;
use App\Models\SignatureMinorAuthorization;
use App\Models\SignatureMinorTerm;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureFieldTypes;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureSigningDataService;
use App\Services\Signature\SignatureStateMachine;
use App\Support\Cpf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * O autoatendimento do Termo de Menores, no tablet pareado.
 *
 *   título → responsável (adulto do título) → CPF completo → menor (do título,
 *   com sobrenome em comum) → documento gerado, congelado e liberado → o resto
 *   é o quiosque de sempre (leitura, aceite, traço, foto).
 *
 * O estado do atendimento (título, responsável confirmado) fica no CACHE do
 * servidor, por tablet, e morre com `flow_ttl_minutes` de inatividade. Nada
 * de pessoa vai para o cache além dos ids: os dados são relidos do
 * MultiClubes a cada passo, e o que o sócio digita (e-mail, RG, CPF do menor)
 * vem junto do pedido do documento.
 *
 * O documento é um SignatureDocument comum, gerado sem atendente: quem
 * aparece como "Gerado por" é o tablet ("Autoatendimento — <nome>"). A
 * identidade é dada como conferida na liberação, porque o CPF completo foi
 * conferido aqui, antes de o documento existir — e isso vai para a trilha.
 */
class MinorTermService
{
    public function __construct(
        private MinorTermMemberDirectory $directory,
        private SignatureDocumentService $documents,
        private SignatureRequestService $requests,
        private SignatureSigningDataService $signingData,
        private SignatureStateMachine $states,
    ) {
    }

    /**
     * O termo vigente hoje, ou recusa.
     *
     * @throws MinorTermException
     */
    public function currentTerm(): SignatureMinorTerm
    {
        $termo = SignatureMinorTerm::current();

        if (!$termo) {
            throw new MinorTermException('Não há termo de menores disponível hoje. Procure a organização do evento.', 409, true);
        }

        return $termo;
    }

    /**
     * Passo 1 — o título digitado. Devolve os maiores de idade para a pessoa
     * escolher o responsável.
     *
     * @return array{term: string, title: string, adults: list<array{id: int, name: string}>}
     *
     * @throws MinorTermException
     */
    public function startTitle(SignatureKioskDevice $device, string $code): array
    {
        $termo = $this->currentTerm();
        $code = trim($code);

        if ($code === '' || mb_strlen($code) > 20) {
            throw new MinorTermException('Informe o número do título.');
        }

        $titulo = $this->title($code);
        $hoje = now();

        $adultos = collect($titulo['people'])
            ->filter(fn(array $p) => $p['birth_date'] !== null && MinorTermRules::isAdult($p['birth_date'], $hoje))
            ->values();

        if ($adultos->isEmpty()) {
            throw new MinorTermException('Não há maior de idade ativo neste título. Procure a secretaria do clube.');
        }

        $this->saveFlow($device, [
            'term_id' => $termo->id,
            'title' => $titulo['code'],
            'responsible_id' => null,
        ]);

        return [
            'term' => $termo->name,
            'title' => $titulo['code'],
            'adults' => $adultos->map(fn(array $p) => ['id' => $p['id'], 'name' => $p['name']])->all(),
        ];
    }

    /**
     * Passo 2 — o responsável escolhido e o CPF COMPLETO dele, conferido aqui.
     *
     * As tentativas são por título neste tablet, e não por atendimento:
     * recomeçar do título não zera o contador.
     *
     * @return array<string, mixed>  o mesmo de `minors()`
     *
     * @throws MinorTermException
     */
    public function confirmResponsible(SignatureKioskDevice $device, int $personId, string $cpf): array
    {
        $fluxo = $this->flow($device);
        $chave = 'minor-term-cpf:' . $device->id . ':' . $fluxo['title'];
        $maximo = max(1, (int) config('signature.minor_terms.max_cpf_attempts', 5));

        if (RateLimiter::tooManyAttempts($chave, $maximo)) {
            $this->endFlow($device);

            throw new MinorTermException(
                'Número de tentativas excedido para este título. Aguarde alguns minutos ou procure a organização do evento.',
                429,
                true,
            );
        }

        $titulo = $this->title($fluxo['title']);
        $pessoa = $this->adult($titulo, $personId);

        if ($pessoa['cpf'] === '') {
            throw new MinorTermException('Não há CPF cadastrado para esta pessoa no clube. Procure a secretaria.');
        }

        if (!Cpf::matches($cpf, $pessoa['cpf'], 'full')) {
            RateLimiter::hit($chave, 15 * 60);
            $restantes = $maximo - RateLimiter::attempts($chave);

            Log::notice('Termo de Menores: CPF do responsável não conferiu.', [
                'tablet' => $device->id,
                'tentativa' => RateLimiter::attempts($chave),
            ]);

            if ($restantes <= 0) {
                $this->endFlow($device);

                throw new MinorTermException(
                    'O CPF não confere. Número de tentativas excedido: procure a organização do evento.',
                    429,
                    true,
                );
            }

            throw new MinorTermException('O CPF não confere. Tente novamente (' . $restantes . ' tentativa(s) restante(s)).');
        }

        RateLimiter::clear($chave);

        $fluxo['responsible_id'] = $pessoa['id'];
        $this->saveFlow($device, $fluxo);

        return $this->minors($device);
    }

    /**
     * Passo 3 — os menores que este responsável pode autorizar. Também é o
     * "Autorizar outro menor", depois da tela verde.
     *
     * @return array{
     *     term: string,
     *     responsible: array{name: string, missing: list<string>},
     *     minors: list<array{id: int, name: string, age: int, authorized: bool, missing: list<string>}>
     * }
     *
     * @throws MinorTermException
     */
    public function minors(SignatureKioskDevice $device): array
    {
        [$fluxo, $termo, $titulo, $responsavel] = $this->confirmedContext($device);

        $menores = $this->eligibleMinors($titulo, $responsavel);

        $autorizados = SignatureMinorAuthorization::authorized()
            ->where('signature_minor_term_id', $termo->id)
            ->whereIn('minor_member_id', $menores->pluck('id'))
            ->pluck('minor_member_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $this->saveFlow($device, $fluxo);

        return [
            'term' => $termo->name,
            'responsible' => [
                'name' => $responsavel['name'],
                'missing' => array_values(array_filter([
                    $responsavel['email'] === null ? 'email' : null,
                    $responsavel['rg'] === null ? 'rg' : null,
                ])),
            ],
            'minors' => $menores->map(fn(array $m) => [
                'id' => $m['id'],
                'name' => $m['name'],
                'age' => MinorTermRules::ageOn($m['birth_date'], now()),
                'authorized' => in_array($m['id'], $autorizados, true),
                'missing' => array_values(array_filter([
                    $m['cpf'] === '' ? 'cpf' : null,
                    $m['rg'] === null ? 'rg' : null,
                ])),
            ])->values()->all(),
        ];
    }

    /**
     * Passo 4 — gera o documento do menor escolhido e abre a sessão do tablet.
     *
     * `extras` traz o que faltava no MultiClubes e a pessoa digitou (ou pulou):
     * responsible_email, responsible_rg, minor_cpf, minor_rg. Vazio = "não
     * informado" no termo. Nada disso volta ao MultiClubes.
     *
     * Menor já autorizado neste termo: não gera nada — devolve
     * `already_authorized`, e a tela diz que está tudo certo.
     *
     * @param  array<string, ?string>  $extras
     * @param  array{ip?: ?string, user_agent?: ?string}  $context
     * @return array{already_authorized: bool, request?: SignatureRequest, session_token?: string, authorization?: SignatureMinorAuthorization}
     *
     * @throws MinorTermException
     */
    public function createDocument(SignatureKioskDevice $device, int $minorId, array $extras, array $context = []): array
    {
        [$fluxo, $termo, $titulo, $responsavel] = $this->confirmedContext($device);

        $menor = $this->eligibleMinors($titulo, $responsavel)->firstWhere('id', $minorId);

        if (!$menor) {
            throw new MinorTermException('Este menor não pode ser autorizado por este responsável.');
        }

        $ja = SignatureMinorAuthorization::authorized()
            ->where('signature_minor_term_id', $termo->id)
            ->where('minor_member_id', $menor['id'])
            ->exists();

        if ($ja) {
            return ['already_authorized' => true];
        }

        $extras = $this->validExtras($extras, $responsavel, $menor);

        $modelo = $termo->currentTemplate();

        if (!$modelo || MinorTermFields::templateProblems($modelo) !== []) {
            Log::error('Termo de Menores: modelo do termo vigente não serve ao autoatendimento.', [
                'termo' => $termo->id,
                'problemas' => $modelo ? MinorTermFields::templateProblems($modelo) : ['sem modelo'],
            ]);

            throw new MinorTermException('O termo deste evento está mal configurado. Procure a organização do evento.', 409, true);
        }

        $this->cancelAbandoned($termo, $menor['id']);

        $emitente = 'Autoatendimento — ' . $device->label();
        $email = $responsavel['email'] ?? $extras['responsible_email'];

        $dados = MinorTermFields::data($modelo, [
            MinorTermFields::RESPONSIBLE_NAME => $responsavel['name'],
            MinorTermFields::RESPONSIBLE_EMAIL => $email,
            MinorTermFields::RESPONSIBLE_CPF => Cpf::format($responsavel['cpf']),
            MinorTermFields::RESPONSIBLE_RG => $responsavel['rg'] ?? $extras['responsible_rg'],
            MinorTermFields::RESPONSIBLE_ADDRESS => $titulo['address'],
            MinorTermFields::MINOR_NAME => $menor['name'],
            MinorTermFields::MINOR_AGE => (string) MinorTermRules::ageOn($menor['birth_date'], now()),
            MinorTermFields::MINOR_CPF => $menor['cpf'] !== '' ? Cpf::format($menor['cpf']) : ($extras['minor_cpf'] !== null ? Cpf::format($extras['minor_cpf']) : null),
            MinorTermFields::MINOR_RG => $menor['rg'] ?? $extras['minor_rg'],
        ]);

        [$autorizacao, $liberacao] = DB::transaction(function () use (
            $device, $termo, $titulo, $responsavel, $menor, $modelo, $dados, $email, $emitente,
        ) {
            $documento = $this->documents->create($modelo, [
                'title' => mb_substr($termo->name . ' - ' . $menor['name'], 0, 200),
                'data' => $dados,
            ], [[
                'name' => $responsavel['name'],
                'cpf' => $responsavel['cpf'],
                'email' => $email,
                'role' => SignatureSigner::ROLE_GUARDIAN,
            ]], null, $emitente);

            $documento = $this->documents->freeze($documento);

            $autorizacao = SignatureMinorAuthorization::create([
                'signature_minor_term_id' => $termo->id,
                'signature_document_id' => $documento->id,
                'signature_kiosk_device_id' => $device->id,
                'title_code' => $titulo['code'],
                'minor_member_id' => $menor['id'],
                'minor_name' => $menor['name'],
                'minor_birth_date' => $menor['birth_date']->toDateString(),
                'responsible_member_id' => $responsavel['id'],
                'responsible_name' => $responsavel['name'],
            ]);

            // Sem nome nem CPF na trilha: os ids bastam para achar o resto.
            $this->states->note($documento, SignatureAuditEvent::EVENT_SELF_SERVICE, [
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'payload' => [
                    'termo' => $termo->id,
                    'tablet' => $device->id,
                    'gerado_por' => $emitente,
                ],
            ]);

            $liberacao = $this->requests->issue($documento->signers()->first(), null, $emitente);

            return [$autorizacao, $liberacao['request']];
        });

        $sessao = $this->requests->consumeIssued(
            $liberacao,
            $context['ip'] ?? null,
            $context['user_agent'] ?? null,
        );

        $solicitacao = $sessao['request'];
        $solicitacao->forceFill(['identity_confirmed_at' => now()])->save();

        $contexto = [
            'signer' => $solicitacao->signature_signer_id,
            'request' => $solicitacao->id,
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];

        $this->states->note($autorizacao->signature_document_id, SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED, $contexto + [
            // CPF completo digitado no autoatendimento, antes de o documento existir.
            'payload' => ['modo' => 'cpf_autoatendimento'],
        ]);

        // A data da assinatura entra antes da leitura, como no QR do balcão.
        $this->signingData->prepare($autorizacao->document()->first(), $contexto);

        // O atendimento sobrevive à sessão de assinatura inteira: o "Autorizar
        // outro menor" da tela verde volta à lista sem pedir o CPF de novo.
        $this->saveFlow($device, $fluxo, (int) config('signature.session_ttl_minutes', 15));

        return [
            'already_authorized' => false,
            'request' => $solicitacao->fresh(),
            'session_token' => $sessao['session_token'],
            'authorization' => $autorizacao,
        ];
    }

    /** "Concluir" no tablet, ou tempo esgotado: esquece o atendimento. */
    public function endFlow(SignatureKioskDevice $device): void
    {
        Cache::forget($this->flowKey($device));
    }

    /**
     * Os dados digitados no tablet para o que faltava. Só vale o que de fato
     * faltava: o que o MultiClubes tem não é sobrescrito pelo tablet.
     *
     * @param  array<string, ?string>  $extras
     * @param  array<string, mixed>  $responsavel
     * @param  array<string, mixed>  $menor
     * @return array{responsible_email: ?string, responsible_rg: ?string, minor_cpf: ?string, minor_rg: ?string}
     *
     * @throws MinorTermException
     */
    private function validExtras(array $extras, array $responsavel, array $menor): array
    {
        $limpo = fn(?string $v) => ($v === null || trim($v) === '') ? null : trim($v);

        $email = $responsavel['email'] === null ? $limpo($extras['responsible_email'] ?? null) : null;
        $rgResponsavel = $responsavel['rg'] === null ? $limpo($extras['responsible_rg'] ?? null) : null;
        $cpfMenor = $menor['cpf'] === '' ? $limpo($extras['minor_cpf'] ?? null) : null;
        $rgMenor = $menor['rg'] === null ? $limpo($extras['minor_rg'] ?? null) : null;

        if ($email !== null && (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new MinorTermException('O e-mail informado não é válido. Corrija ou pule.');
        }

        if ($cpfMenor !== null) {
            [$valor, $erro] = SignatureFieldTypes::parse(['type' => SignatureFieldTypes::CPF], $cpfMenor);

            if ($erro !== null) {
                throw new MinorTermException('O CPF do menor não é válido. Corrija ou pule.');
            }

            $cpfMenor = $valor;
        }

        foreach (['RG do responsável' => $rgResponsavel, 'RG do menor' => $rgMenor] as $rotulo => $rg) {
            if ($rg !== null && mb_strlen($rg) > 20) {
                throw new MinorTermException('O ' . $rotulo . ' ficou longo demais. Corrija ou pule.');
            }
        }

        return [
            'responsible_email' => $email,
            'responsible_rg' => $rgResponsavel,
            'minor_cpf' => $cpfMenor,
            'minor_rg' => $rgMenor,
        ];
    }

    /**
     * Documento deste menor, neste termo, que ficou no meio (a pessoa saiu
     * antes de assinar): é cancelado para não sobrar um "Aguardando
     * assinatura" pendurado ao lado do novo.
     */
    private function cancelAbandoned(SignatureMinorTerm $termo, int $minorId): void
    {
        $abertos = SignatureMinorAuthorization::with('document')
            ->where('signature_minor_term_id', $termo->id)
            ->where('minor_member_id', $minorId)
            ->whereHas('document', fn($q) => $q->where('status', SignatureDocument::STATUS_AWAITING_SIGNATURE))
            ->get();

        foreach ($abertos as $autorizacao) {
            $this->documents->cancel($autorizacao->document, 'Substituído por novo atendimento no autoatendimento.');
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: SignatureMinorTerm, 2: array<string, mixed>, 3: array<string, mixed>}
     *
     * @throws MinorTermException
     */
    private function confirmedContext(SignatureKioskDevice $device): array
    {
        $fluxo = $this->flow($device);

        if (empty($fluxo['responsible_id'])) {
            throw new MinorTermException('Confirme o responsável antes de escolher o menor.', 409, true);
        }

        $termo = $this->currentTerm();

        // Virou o dia (ou o termo foi trocado) no meio do atendimento.
        if ($termo->id !== (int) $fluxo['term_id']) {
            $this->endFlow($device);

            throw new MinorTermException('O termo disponível mudou. Recomece o atendimento.', 409, true);
        }

        $titulo = $this->title($fluxo['title']);
        $responsavel = $this->adult($titulo, (int) $fluxo['responsible_id']);

        return [$fluxo, $termo, $titulo, $responsavel];
    }

    /**
     * @param  array<string, mixed>  $titulo
     * @param  array<string, mixed>  $responsavel
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function eligibleMinors(array $titulo, array $responsavel)
    {
        $hoje = now();

        return collect($titulo['people'])
            ->filter(fn(array $p) => $p['id'] !== $responsavel['id']
                && $p['birth_date'] !== null
                && !MinorTermRules::isAdult($p['birth_date'], $hoje)
                && MinorTermRules::shareSurname($responsavel['name'], $p['name']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $titulo
     * @return array<string, mixed>
     *
     * @throws MinorTermException
     */
    private function adult(array $titulo, int $personId): array
    {
        $pessoa = collect($titulo['people'])->firstWhere('id', $personId);

        if (!$pessoa || $pessoa['birth_date'] === null || !MinorTermRules::isAdult($pessoa['birth_date'], now())) {
            throw new MinorTermException('Escolha um responsável maior de idade deste título.');
        }

        return $pessoa;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MinorTermException
     */
    private function title(string $code): array
    {
        try {
            $titulo = $this->directory->title($code);
        } catch (MinorTermDirectoryUnavailable) {
            throw new MinorTermException('O cadastro de sócios não respondeu. Tente de novo em instantes ou procure a organização do evento.', 503);
        }

        if (!$titulo) {
            throw new MinorTermException('Título não encontrado ou inativo. Confira o número e tente de novo.');
        }

        return $titulo;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MinorTermException
     */
    private function flow(SignatureKioskDevice $device): array
    {
        $fluxo = Cache::get($this->flowKey($device));

        if (!is_array($fluxo)) {
            throw new MinorTermException('O atendimento expirou. Comece de novo pelo número do título.', 409, true);
        }

        return $fluxo;
    }

    /**
     * Grava (e renova o prazo de) o atendimento em curso neste tablet.
     *
     * @param  int  $extraMinutes  somado à inatividade — a sessão de assinatura, depois do documento
     */
    private function saveFlow(SignatureKioskDevice $device, array $flow, int $extraMinutes = 0): void
    {
        Cache::put(
            $this->flowKey($device),
            $flow,
            now()->addMinutes(max(1, (int) config('signature.minor_terms.flow_ttl_minutes', 10)) + max(0, $extraMinutes)),
        );
    }

    private function flowKey(SignatureKioskDevice $device): string
    {
        return 'minor-term-flow:' . $device->id;
    }
}
