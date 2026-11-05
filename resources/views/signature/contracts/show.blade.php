{{--
    Tela do contrato: a leitura (prévia), a validação da Gerência (aprovar ou
    devolver com observações por cláusula), o que mudou desde a última
    devolução, anexos, versões, histórico e o documento de assinatura.

    05/11/2026: as duas colunas rolam cada uma por si (split-xl); o andamento da
    assinatura aparece aqui (quem assinou e quem falta); o presidente, com o contrato
    aguardando a assinatura dele, pode recusar e devolver para revisão (contracts.refuse-signature).
    Cartão "Cancelar ou arquivar" (05/11/2026): cancelar até a assinatura terminar (cancela o documento
    junto), arquivar qualquer um; os dois com motivo obrigatório.
--}}
@php
    use App\Models\SignatureContract as C;
    use App\Models\SignatureContractVersion as V;

    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $card = 'space-y-3 rounded-card bg-surface p-5 shadow-card';
    $h = 'font-display text-base font-semibold text-ink';
    $btn = 'inline-flex h-10 items-center rounded-full px-4 text-sm font-bold transition';
    $kind = match ($contract->status) {
        C::STATUS_IN_REVIEW => 'warn',
        C::STATUS_RETURNED => 'info',
        C::STATUS_APPROVED => 'ok',
        C::STATUS_CANCELED => 'danger',
        default => 'off',
    };
    $podeDecidir = auth()->user()->can('decide', $contract);
    $podeEditar = auth()->user()->can('update', $contract);
    $podeEnviar = auth()->user()->can('submit', $contract);
    $podeGerir = auth()->user()->can('manage', $contract);
    $anexosAbertos = in_array($contract->status, [C::STATUS_DRAFT, C::STATUS_RETURNED, C::STATUS_IN_REVIEW], true);
    $erros = collect($problems)->where('level', 'error');
    $abertas = $contract->comments->whereNull('resolved_at');
    $documento = $contract->document;
    $podeRecusar = auth()->user()->can('refuseSignature', $contract);
    $docEncerrado = $documento && in_array($documento->status, [\App\Models\SignatureDocument::STATUS_CANCELED, \App\Models\SignatureDocument::STATUS_REFUSED], true);
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page>
        <x-page-title :title="($contract->number ? $contract->number . ' — ' : '') . $contract->title" :back="route('contracts.index')">
            <x-pill :kind="$kind">{{ $contract->statusLabel() }}</x-pill>
            <span class="ml-1">setor {{ $contract->sector_name ?? '—' }} · redigido por {{ $contract->created_by_name ?? '—' }}</span>
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('contracts.docx', $contract) }}"><x-icon name="download" /> Word</x-secondary-button-a>
                @if($podeEditar)
                    <x-secondary-button-a href="{{ route('contracts.edit', $contract) }}"><x-icon name="pencil" /> {{ $contract->status === C::STATUS_IN_REVIEW ? 'Ajustar o texto' : 'Editar' }}</x-secondary-button-a>
                @endif
                @if($podeEnviar)
                    <form method="POST" action="{{ route('contracts.submit', $contract) }}" onsubmit="return confirm('Enviar à Gerência para validação?')">
                        @csrf
                        <x-primary-button>Enviar à Gerência</x-primary-button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($contract->isArchived())
            <div class="rounded-card bg-subtle px-5 py-3 text-sm text-ink-2">
                <b class="text-ink">Arquivado</b> em {{ $contract->archived_at->format('d/m/Y H:i') }} por {{ $contract->archived_by_name ?? '—' }}:
                ele só aparece na aba Arquivados da lista. Nada mudou no contrato.
            </div>
        @endif

        @if(session('contractProblems'))
            <div class="{{ $card }}">
                <p class="{{ $h }}">O que falta</p>
                @include('signature.contracts.partials.problems', ['problems' => session('contractProblems')])
            </div>
        @endif

        <div class="split-xl grid grid-cols-1 gap-6 xl:grid-cols-5">
            <div class="split-pane space-y-6 xl:col-span-3">

                {{-- O presidente recusa a assinatura e devolve para revisão (05/11/2026). --}}
                @if($podeRecusar)
                    <form method="POST" action="{{ route('contracts.refuse-signature', $contract) }}" class="{{ $card }} border-2 border-warn"
                          onsubmit="return confirm('Recusar a assinatura? O contrato volta para quem redigiu, com o seu motivo, e as assinaturas recomeçam do zero quando ele for aprovado de novo.')">
                        @csrf
                        <p class="{{ $h }}">Aguardando a sua assinatura</p>
                        <p class="text-sm text-ink-2">
                            Você assina este contrato pelo clube no tablet da Diretoria. Se não concorda com o texto, recuse aqui
                            dizendo o que precisa mudar: o contrato volta para quem redigiu, passa de novo pela Gerência e, aprovado,
                            gera um documento novo — quem já assinou assina de novo.
                        </p>
                        <div>
                            <label class="mb-1 block text-xs font-bold text-ink-2" for="refuse_reason">Motivo da recusa</label>
                            <textarea id="refuse_reason" name="reason" rows="3" maxlength="2000" required class="{{ $f }}"
                                      placeholder="Ex.: a cláusula 4 precisa prever a multa por atraso; o prazo de vigência é de 6 meses, não 12.">{{ old('reason') }}</textarea>
                            @error('reason')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="{{ $btn }} bg-danger text-white hover:opacity-90">Recusar e devolver para revisão</button>
                        </div>
                    </form>
                @endif

                {{-- Validação da Gerência --}}
                @if($podeDecidir)
                    <form method="POST" action="{{ route('contracts.decide', $contract) }}" class="{{ $card }} border-2 border-warn">
                        @csrf
                        <p class="{{ $h }}">Validação da Gerência</p>
                        <p class="text-sm text-ink-2">
                            Leia o contrato abaixo. Para pedir mudanças, escreva as observações (gerais ou por cláusula) e devolva.
                            Se preferir ajustar você mesmo, use "Ajustar o texto" e depois aprove.
                        </p>
                        @if($erros->isNotEmpty())
                            <p class="rounded-xl bg-danger-soft px-3 py-2 text-sm text-ink">
                                Há {{ $erros->count() }} {{ $erros->count() === 1 ? 'problema' : 'problemas' }} que impedem a aprovação (ver "Conferência").
                            </p>
                        @endif
                        <div>
                            <label class="mb-1 block text-xs font-bold text-ink-2" for="note">Observação geral</label>
                            <textarea id="note" name="note" rows="3" maxlength="3000" class="{{ $f }}">{{ old('note') }}</textarea>
                        </div>
                        <details @if(old('comments')) open @endif>
                            <summary class="cursor-pointer text-sm font-bold text-grena-ink">Observações por cláusula</summary>
                            <div class="mt-3 space-y-3">
                                @foreach($contract->content['clauses'] ?? [] as $i => $clausula)
                                    <div>
                                        <label class="mb-1 block text-xs font-bold text-ink-2" for="cm-{{ $clausula['uid'] }}">
                                            Cláusula {{ $i + 1 }} – {{ $clausula['title'] ?: 'sem título' }}
                                            <a href="#ct-{{ $clausula['uid'] }}" class="font-normal text-grena-ink hover:underline">ver</a>
                                        </label>
                                        <textarea id="cm-{{ $clausula['uid'] }}" name="comments[{{ $clausula['uid'] }}]" rows="2" maxlength="3000" class="{{ $f }}">{{ old('comments.' . $clausula['uid']) }}</textarea>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                        {{-- Visto da Gerência (04/11/2026): obrigatório para aprovar; sai em todas as páginas do documento. --}}
                        <div class="rounded-xl border border-line p-3 text-ink">
                            @include('signature.contracts.partials.initials-pad', ['id' => 'vistoGerencia'])
                            <p class="mt-2 text-xs text-ink-2">Só para aprovar. O visto aparece no pé de cada página do documento de assinatura, ao lado dos vistos de quem assina.</p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" name="decision" value="return" class="{{ $btn }} bg-subtle text-ink hover:bg-line">Devolver com observações</button>
                            <button type="submit" name="decision" value="approve" class="{{ $btn }} bg-ok text-white hover:bg-ok/90 dark:text-canvas" @disabled($erros->isNotEmpty())
                                    data-approve-with-initials>
                                Aprovar
                            </button>
                        </div>
                    </form>
                    <script>
                        document.querySelector('[data-approve-with-initials]')?.addEventListener('click', function (e) {
                            var pad = window.laraInitialsPad(document.getElementById('vistoGerencia'));
                            if (pad.points() < {{ (int) config('signature.evidence.min_initials_points', 8) }}) {
                                e.preventDefault();
                                alert('Faça o seu visto no quadro antes de aprovar.');
                                return;
                            }
                            if (!confirm('Aprovar? O contrato ganha número, o seu visto vai em todas as páginas e o documento de assinatura é gerado em rascunho.')) {
                                e.preventDefault();
                            }
                        });
                    </script>
                @endif

                {{-- Revisão automática pelo Claude --}}
                @if($aiEnabled && ($aiReview || auth()->user()->can('aiReview', $contract)))
                    @include('signature.contracts.partials.ai-review', ['podeDecidir' => $podeDecidir])
                @endif

                {{-- O que mudou desde a última devolução --}}
                @if($diff && $diff['changed'])
                    <div class="{{ $card }} ct-diff">
                        <p class="{{ $h }}">O que mudou desde a versão {{ $diffVersion->version }} (devolvida)</p>
                        @if($diff['fields'] !== [])
                            <ul class="list-disc space-y-1 pl-5 text-sm text-ink">
                                @foreach($diff['fields'] as $linha)
                                    <li>{{ $linha }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @foreach($diff['removed'] as $titulo)
                            <p class="text-sm"><del>Cláusula removida: {{ $titulo }}</del></p>
                        @endforeach
                        @foreach($diff['clauses'] as $c)
                            @continue($c['status'] === 'same')
                            <div class="rounded-xl border border-line p-3 text-sm">
                                <p class="font-bold text-ink">
                                    <a href="#ct-{{ $c['uid'] }}" class="hover:underline">Cláusula {{ $c['number'] }} – {{ $c['title'] }}</a>
                                    @if($c['status'] === 'added') <span class="ml-1 rounded-full bg-ok-soft px-2 text-xs text-ok">nova</span> @endif
                                </p>
                                @if($c['title_diff'])
                                    <p class="text-xs text-ink-2">Título: {!! $c['title_diff'] !!}</p>
                                @endif
                                @foreach($c['items'] as $item)
                                    <p class="mt-1 text-ink">{!! $item['html'] !!}</p>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="max-w-full overflow-x-auto rounded-card shadow-card">
                    <div class="ct-paper">{!! $preview !!}</div>
                </div>
            </div>

            <aside class="split-pane space-y-4 xl:col-span-2">
                {{-- Documento de assinatura e o andamento (05/11/2026: acompanhar dentro de Contratos) --}}
                @if($contract->status === C::STATUS_APPROVED)
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Assinatura</p>
                        @if($documento)
                            <p class="text-sm text-ink">
                                @if(auth()->user()->can('view', $documento))
                                    <a href="{{ route('contract-signatures.show', $documento) }}" class="font-bold text-grena-ink hover:underline">{{ $documento->title }}</a>
                                @else
                                    <b>{{ $documento->title }}</b>
                                @endif
                            </p>
                            @include('signature.contracts.partials.signing-progress', ['contract' => $contract])
                            @if($documento->status === \App\Models\SignatureDocument::STATUS_DRAFT)
                                <p class="text-xs text-ink-2">Em rascunho: confira os signatários e os anexos e congele para colher as assinaturas.</p>
                            @elseif($documento->status === \App\Models\SignatureDocument::STATUS_REFUSED)
                                <p class="text-xs text-ink-2">Recusado por uma testemunha ou um fiador: gere um documento novo para colher as assinaturas de novo, ou reabra o contrato para alterar.</p>
                            @endif
                        @else
                            <p class="text-sm text-ink-2">Sem documento de assinatura.</p>
                        @endif
                        @if($podeGerir && (!$documento || $docEncerrado))
                            <form method="POST" action="{{ route('contracts.document', $contract) }}">
                                @csrf
                                <x-primary-button size="sm">Gerar documento de assinatura</x-primary-button>
                            </form>
                        @endif
                        <div class="flex flex-wrap gap-2 border-t border-line pt-3">
                            @can('addendum', $contract)
                                <form method="POST" action="{{ route('contracts.addendum', $contract) }}" onsubmit="return confirm('Criar um aditivo deste contrato?')">
                                    @csrf
                                    <x-secondary-button type="submit" size="sm">Criar aditivo</x-secondary-button>
                                </form>
                            @endcan
                            @if($podeGerir && (!$documento || !$documento->isFrozen() || $docEncerrado))
                                <form method="POST" action="{{ route('contracts.reopen', $contract) }}"
                                      onsubmit="return confirm('Reabrir para alteração? O rascunho do documento de assinatura é cancelado e o contrato precisa ser validado de novo.')">
                                    @csrf
                                    <x-secondary-button type="submit" size="sm">Reabrir para alteração</x-secondary-button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Conferência --}}
                @if(in_array($contract->status, [C::STATUS_DRAFT, C::STATUS_RETURNED, C::STATUS_IN_REVIEW], true))
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Conferência</p>
                        @include('signature.contracts.partials.problems', ['problems' => $problems])
                    </div>
                @endif

                {{-- Observações --}}
                @if($contract->comments->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Observações da Gerência
                            @if($abertas->isNotEmpty()) <span class="text-xs font-normal text-warn">({{ $abertas->count() }} em aberto)</span> @endif
                        </p>
                        @foreach($contract->comments->sortByDesc('id') as $comment)
                            <div @class(['rounded-xl px-3 py-2 text-sm', 'bg-warn-soft' => !$comment->isResolved(), 'bg-subtle text-ink-2' => $comment->isResolved()])>
                                <p class="text-xs font-bold">
                                    {{ $comment->clause_uid ? ($outline[$comment->clause_uid]['label'] ?? 'Cláusula removida') : 'Geral' }}
                                    · {{ $comment->author_name }} · {{ $comment->created_at?->format('d/m H:i') }}
                                </p>
                                <p class="whitespace-pre-line">{{ $comment->body }}</p>
                                <form method="POST" action="{{ route('contracts.comments.resolve', [$contract, $comment]) }}" class="mt-1">
                                    @csrf
                                    <input type="hidden" name="resolved" value="{{ $comment->isResolved() ? 0 : 1 }}">
                                    <button type="submit" class="text-xs font-bold text-grena-ink hover:underline">
                                        {{ $comment->isResolved() ? 'Resolvida por ' . $comment->resolved_by_name . ' — reabrir' : 'Marcar como resolvida' }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Anexos --}}
                <div class="{{ $card }}">
                    <p class="{{ $h }}">Anexos</p>
                    <p class="text-xs text-ink-3">Propostas, projetos: seguem junto para a assinatura quando o contrato é aprovado.</p>
                    @forelse($contract->attachments as $anexo)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <a href="{{ route('contracts.attachments.show', [$contract, $anexo]) }}" target="_blank" class="min-w-0 truncate font-bold text-grena-ink hover:underline">{{ $anexo->label }}</a>
                            @if($anexosAbertos && $podeGerir)
                                <form method="POST" action="{{ route('contracts.attachments.destroy', [$contract, $anexo]) }}" onsubmit="return confirm('Remover o anexo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-ink-2">Nenhum anexo.</p>
                    @endforelse
                    @if($anexosAbertos && $podeGerir)
                        <form method="POST" action="{{ route('contracts.attachments.store', $contract) }}" enctype="multipart/form-data" class="space-y-2 border-t border-line pt-3">
                            @csrf
                            <input type="text" name="label" maxlength="120" class="{{ $f }}" placeholder="Anexo I — Proposta nº 3653498" required>
                            <input type="file" name="file" accept="application/pdf,image/jpeg,image/png" class="block w-full text-sm" required>
                            <x-secondary-button type="submit" size="sm">Incluir anexo</x-secondary-button>
                        </form>
                    @endif
                </div>

                {{-- Vigência (05/11/2026): prazo a partir da assinatura ou data fixa; aviso a 60, 30 e 15 dias. --}}
                @php
                    $vigenciaRotulo = $contract->validityLabel();
                    $estadoVigencia = $contract->expiryState();
                    $diasVigencia = $contract->daysToExpiry();
                    $vig = \App\Services\Signature\Contracts\ContractContent::validity($contract->content['validity'] ?? null);
                @endphp
                @if($vigenciaRotulo || $contract->expires_on || ($podeGerir && $contract->isSigned()))
                    <div class="{{ $card }}">
                        <div class="flex items-center justify-between gap-2">
                            <p class="{{ $h }}">Vigência</p>
                            @if($estadoVigencia === 'expired')
                                <x-pill kind="danger">Vencido</x-pill>
                            @elseif($estadoVigencia === 'expiring')
                                <x-pill kind="warn">Vence em {{ $diasVigencia }} {{ $diasVigencia === 1 ? 'dia' : 'dias' }}</x-pill>
                            @endif
                        </div>
                        @if($contract->expires_on)
                            <p class="text-sm">
                                Até <b>{{ $contract->expires_on->format('d/m/Y') }}</b>
                                <span class="text-ink-2">· assinado em {{ $contract->signed_at?->format('d/m/Y') }} · {{ $vigenciaRotulo }}</span>
                            </p>
                        @elseif($vigenciaRotulo)
                            <p class="text-sm">{{ ucfirst($vigenciaRotulo) }}. <span class="text-ink-2">O término é calculado quando todos assinarem.</span></p>
                        @else
                            <p class="text-sm text-ink-2">Sem vigência informada: não há aviso de término.</p>
                        @endif

                        @if($podeGerir && $contract->isSigned())
                            <details @if($errors->has('validity.*')) open @endif>
                                <summary class="cursor-pointer text-xs font-bold text-grena-ink">{{ $vigenciaRotulo ? 'Mudar a vigência' : 'Informar a vigência' }}</summary>
                                <form method="POST" action="{{ route('contracts.validity', $contract) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label for="v_months" class="block text-xs font-bold text-ink-2">Meses a partir da assinatura</label>
                                            <input type="number" id="v_months" name="validity[months]" min="1" max="{{ \App\Services\Signature\Contracts\ContractContent::MAX_VALIDITY_MONTHS }}" value="{{ old('validity.months', $vig['months']) }}" class="{{ $f }}">
                                        </div>
                                        <div>
                                            <label for="v_until" class="block text-xs font-bold text-ink-2">ou termina em</label>
                                            <input type="date" id="v_until" name="validity[until]" value="{{ old('validity.until', $vig['until']) }}" class="{{ $f }}">
                                        </div>
                                    </div>
                                    @error('validity.months')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                                    @error('validity.until')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                                    <p class="text-xs text-ink-3">Só a vigência muda: o texto e o documento assinado ficam como estão. Os avisos de término recomeçam.</p>
                                    <x-secondary-button type="submit" size="sm">Salvar vigência</x-secondary-button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endif

                {{-- Aditivos. Marcar/desmarcar como aditivo antes da aprovação (05/11/2026): só completa. --}}
                @php $podeMudarTipo = auth()->user()->can('changeKind', $contract); @endphp
                @if($contract->parent || $contract->addenda->isNotEmpty() || ($podeMudarTipo && $addendumTargets->isNotEmpty()))
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Contrato e aditivos</p>
                        @if($contract->parent)
                            <p class="text-sm">Aditivo de
                                @can('view', $contract->parent)
                                    <a href="{{ route('contracts.show', $contract->parent) }}" class="font-bold text-grena-ink hover:underline">{{ $contract->parent->number }} — {{ $contract->parent->title }}</a>
                                @else
                                    <b>{{ $contract->parent->number }} — {{ $contract->parent->title }}</b>
                                @endcan
                            </p>
                            @if(!empty($contract->content['addendum']['base_number']) && $contract->content['addendum']['base_number'] !== $contract->parent->number)
                                <p class="text-xs text-ink-2">As variáveis do original vêm do último aditivo assinado: {{ $contract->content['addendum']['base_number'] }}.</p>
                            @endif
                            @if($podeMudarTipo)
                                <form method="POST" action="{{ route('contracts.unlink-addendum', $contract) }}"
                                      onsubmit="return confirm('Deixar de ser aditivo? O que veio do contrato original continua no texto.')">
                                    @csrf
                                    <button type="submit" class="text-xs font-bold text-danger hover:underline">Desmarcar como aditivo</button>
                                </form>
                            @endif
                        @elseif($podeMudarTipo && $addendumTargets->isNotEmpty())
                            <form method="POST" action="{{ route('contracts.link-addendum', $contract) }}" class="space-y-2"
                                  onsubmit="return confirm('Marcar como aditivo? Entram as partes, as variáveis e quem assina pela contratada que faltam; o que já está aqui fica.')">
                                @csrf
                                <label for="link_parent" class="block text-xs font-bold text-ink-2">É aditivo de um contrato assinado?</label>
                                <select id="link_parent" name="parent_id" class="{{ $f }}" required>
                                    <option value="">Escolha o contrato…</option>
                                    @foreach($addendumTargets as $alvo)
                                        <option value="{{ $alvo->id }}">{{ $alvo->number }} — {{ $alvo->title }}</option>
                                    @endforeach
                                </select>
                                <x-secondary-button type="submit" size="sm">Marcar como aditivo</x-secondary-button>
                            </form>
                        @endif
                        @foreach($contract->addenda as $aditivo)
                            <p class="text-sm"><a href="{{ route('contracts.show', $aditivo) }}" class="font-bold text-grena-ink hover:underline">{{ $aditivo->number ?? 'Aditivo sem número' }}</a> — {{ $aditivo->statusLabel() }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Versões --}}
                @if($contract->versions->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Versões enviadas à Gerência</p>
                        @foreach($contract->versions as $versao)
                            <div class="text-sm">
                                <p><b>v{{ $versao->version }}</b> · {{ $versao->decisionLabel() }}</p>
                                <p class="text-xs text-ink-2">
                                    {{ $versao->by_reviewer ? 'Ajuste da Gerência' : 'Enviada' }} por {{ $versao->submitted_by_name ?? '—' }} em {{ $versao->submitted_at?->format('d/m/Y H:i') }}
                                    @if($versao->decided_at) · decidida por {{ $versao->decided_by_name }} em {{ $versao->decided_at->format('d/m/Y H:i') }} @endif
                                </p>
                                @if($versao->note && !$versao->by_reviewer)
                                    <p class="text-xs text-ink-2">Recado: {{ $versao->note }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Modelo --}}
                @can('manageLibrary', C::class)
                    <form method="POST" action="{{ route('contracts.save-model', $contract) }}" class="{{ $card }}">
                        @csrf
                        <p class="{{ $h }}">Salvar como modelo do setor</p>
                        <p class="text-xs text-ink-3">Leva título, termos, variáveis (sem valor), considerandos e cláusulas. Não leva as partes nem os signatários.</p>
                        <input type="text" name="name" maxlength="150" class="{{ $f }}" placeholder="Prestação de serviços — manutenção" required>
                        <x-secondary-button type="submit" size="sm">Salvar modelo</x-secondary-button>
                    </form>
                @endcan

                {{-- Histórico --}}
                <div class="{{ $card }}">
                    <p class="{{ $h }}">Histórico</p>
                    @foreach($contract->events as $evento)
                        <p class="text-sm text-ink">
                            {{ $evento->label() }}
                            <span class="block text-xs text-ink-2">{{ $evento->actor_name ?? 'Sistema' }} · {{ $evento->created_at?->format('d/m/Y H:i') }}</span>
                        </p>
                    @endforeach
                </div>

                {{-- Cancelar (até a assinatura terminar) e arquivar (qualquer um), motivo obrigatório (05/11/2026). --}}
                @php
                    $podeCancelar = auth()->user()->can('cancel', $contract);
                    $podeArquivar = auth()->user()->can('archive', $contract);
                @endphp
                @if($podeCancelar || $podeArquivar)
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Cancelar ou arquivar</p>

                        @if($podeCancelar)
                            <details class="group" @if(old('_acao') === 'cancelar') open @endif>
                                <summary class="cursor-pointer text-sm font-bold text-danger">Cancelar o contrato</summary>
                                <form method="POST" action="{{ route('contracts.cancel', $contract) }}" class="mt-2 space-y-2"
                                      onsubmit="return confirm('Cancelar este contrato? {{ $contract->status === C::STATUS_APPROVED ? 'O documento de assinatura é cancelado junto: quem ainda não assinou não assina mais. ' : '' }}Não dá para desfazer.')">
                                    @csrf
                                    <input type="hidden" name="_acao" value="cancelar">
                                    <p class="text-xs text-ink-2">
                                        @if($contract->status === C::STATUS_APPROVED)
                                            O documento de assinatura é cancelado junto: quem ainda não assinou não assina mais.
                                        @endif
                                        Quem redigiu recebe o motivo por e-mail. Não dá para desfazer.
                                    </p>
                                    <label for="cancel_reason" class="block text-xs font-bold text-ink-2">Motivo do cancelamento</label>
                                    <textarea id="cancel_reason" name="reason" rows="2" maxlength="255" required class="{{ $f }}"
                                              placeholder="Ex.: o fornecedor desistiu; o serviço não será mais contratado.">{{ old('_acao') === 'cancelar' ? old('reason') : '' }}</textarea>
                                    @if(old('_acao') === 'cancelar') @error('reason')<p class="text-xs text-danger">{{ $message }}</p>@enderror @endif
                                    <button type="submit" class="{{ $btn }} bg-danger text-white hover:opacity-90">Cancelar o contrato</button>
                                </form>
                            </details>
                        @endif

                        @if($podeArquivar)
                            @if($contract->isArchived())
                                <p class="text-sm text-ink-2">Arquivado por {{ $contract->archived_by_name ?? '—' }} em {{ $contract->archived_at->format('d/m/Y H:i') }}.</p>
                                <form method="POST" action="{{ route('contracts.unarchive', $contract) }}">
                                    @csrf
                                    <x-secondary-button type="submit" size="sm">Desarquivar</x-secondary-button>
                                </form>
                            @else
                                <details class="group" @if(old('_acao') === 'arquivar') open @endif>
                                    <summary class="cursor-pointer text-sm font-bold text-ink">Arquivar</summary>
                                    <form method="POST" action="{{ route('contracts.archive', $contract) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="hidden" name="_acao" value="arquivar">
                                        <p class="text-xs text-ink-2">O contrato sai das abas da lista e vai para <b>Arquivados</b>. Nada muda nele, e dá para desarquivar.</p>
                                        <label for="archive_reason" class="block text-xs font-bold text-ink-2">Motivo</label>
                                        <textarea id="archive_reason" name="reason" rows="2" maxlength="255" required class="{{ $f }}"
                                                  placeholder="Ex.: contrato encerrado; rascunho que não vai seguir.">{{ old('_acao') === 'arquivar' ? old('reason') : '' }}</textarea>
                                        @if(old('_acao') === 'arquivar') @error('reason')<p class="text-xs text-danger">{{ $message }}</p>@enderror @endif
                                        <x-secondary-button type="submit" size="sm">Arquivar</x-secondary-button>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </div>
                @endif
            </aside>
        </div>
    </x-page>
</x-app-layout>
