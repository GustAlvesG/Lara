{{--
    Tela do contrato: a leitura (prévia), a validação da Gerência (aprovar ou
    devolver com observações por cláusula), o que mudou desde a última
    devolução, anexos, versões, histórico e o documento de assinatura.
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
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page>
        <x-page-title :title="($contract->number ? $contract->number . ' — ' : '') . $contract->title" :back="route('signature-contracts.index')">
            <x-pill :kind="$kind">{{ $contract->statusLabel() }}</x-pill>
            <span class="ml-1">setor {{ $contract->sector_name ?? '—' }} · redigido por {{ $contract->created_by_name ?? '—' }}</span>
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('signature-contracts.docx', $contract) }}"><x-icon name="download" /> Word</x-secondary-button-a>
                @if($podeEditar)
                    <x-secondary-button-a href="{{ route('signature-contracts.edit', $contract) }}"><x-icon name="pencil" /> {{ $contract->status === C::STATUS_IN_REVIEW ? 'Ajustar o texto' : 'Editar' }}</x-secondary-button-a>
                @endif
                @if($podeEnviar)
                    <form method="POST" action="{{ route('signature-contracts.submit', $contract) }}" onsubmit="return confirm('Enviar à Gerência para validação?')">
                        @csrf
                        <x-primary-button>Enviar à Gerência</x-primary-button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if(session('contractProblems'))
            <div class="{{ $card }}">
                <p class="{{ $h }}">O que falta</p>
                @include('signature.contracts.partials.problems', ['problems' => session('contractProblems')])
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <div class="space-y-6 xl:col-span-3">

                {{-- Validação da Gerência --}}
                @if($podeDecidir)
                    <form method="POST" action="{{ route('signature-contracts.decide', $contract) }}" class="{{ $card }} border-2 border-warn">
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
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" name="decision" value="return" class="{{ $btn }} bg-subtle text-ink hover:bg-line">Devolver com observações</button>
                            <button type="submit" name="decision" value="approve" class="{{ $btn }} bg-ok text-white hover:bg-ok/90 dark:text-canvas" @disabled($erros->isNotEmpty())
                                    onclick="return confirm('Aprovar? O contrato ganha número e o documento de assinatura é gerado em rascunho.')">
                                Aprovar
                            </button>
                        </div>
                    </form>
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

            <aside class="space-y-4 xl:col-span-2">
                {{-- Documento de assinatura --}}
                @if($contract->status === C::STATUS_APPROVED)
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Documento de assinatura</p>
                        @if($documento)
                            <p class="text-sm text-ink">
                                <a href="{{ route('signature-documents.show', $documento) }}" class="font-bold text-grena-ink hover:underline">{{ $documento->title }}</a>
                                <br><span class="text-xs text-ink-2">{{ $documento->statusLabel() }}</span>
                            </p>
                            @if($documento->status === \App\Models\SignatureDocument::STATUS_DRAFT)
                                <p class="text-xs text-ink-2">Em rascunho: confira os signatários e os anexos e congele para colher as assinaturas.</p>
                            @endif
                        @else
                            <p class="text-sm text-ink-2">Sem documento de assinatura.</p>
                        @endif
                        @if($podeGerir && (!$documento || $documento->status === \App\Models\SignatureDocument::STATUS_CANCELED))
                            <form method="POST" action="{{ route('signature-contracts.document', $contract) }}">
                                @csrf
                                <x-primary-button size="sm">Gerar documento de assinatura</x-primary-button>
                            </form>
                        @endif
                        <div class="flex flex-wrap gap-2 border-t border-line pt-3">
                            @can('addendum', $contract)
                                <form method="POST" action="{{ route('signature-contracts.addendum', $contract) }}" onsubmit="return confirm('Criar um aditivo deste contrato?')">
                                    @csrf
                                    <x-secondary-button type="submit" size="sm">Criar aditivo</x-secondary-button>
                                </form>
                            @endcan
                            @if($podeGerir && (!$documento || !$documento->isFrozen() || $documento->status === \App\Models\SignatureDocument::STATUS_CANCELED))
                                <form method="POST" action="{{ route('signature-contracts.reopen', $contract) }}"
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
                                <form method="POST" action="{{ route('signature-contracts.comments.resolve', [$contract, $comment]) }}" class="mt-1">
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
                            <a href="{{ route('signature-contracts.attachments.show', [$contract, $anexo]) }}" target="_blank" class="min-w-0 truncate font-bold text-grena-ink hover:underline">{{ $anexo->label }}</a>
                            @if($anexosAbertos && $podeGerir)
                                <form method="POST" action="{{ route('signature-contracts.attachments.destroy', [$contract, $anexo]) }}" onsubmit="return confirm('Remover o anexo?')">
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
                        <form method="POST" action="{{ route('signature-contracts.attachments.store', $contract) }}" enctype="multipart/form-data" class="space-y-2 border-t border-line pt-3">
                            @csrf
                            <input type="text" name="label" maxlength="120" class="{{ $f }}" placeholder="Anexo I — Proposta nº 3653498" required>
                            <input type="file" name="file" accept="application/pdf,image/jpeg,image/png" class="block w-full text-sm" required>
                            <x-secondary-button type="submit" size="sm">Incluir anexo</x-secondary-button>
                        </form>
                    @endif
                </div>

                {{-- Aditivos --}}
                @if($contract->parent || $contract->addenda->isNotEmpty())
                    <div class="{{ $card }}">
                        <p class="{{ $h }}">Contrato e aditivos</p>
                        @if($contract->parent)
                            <p class="text-sm">Aditivo de <a href="{{ route('signature-contracts.show', $contract->parent) }}" class="font-bold text-grena-ink hover:underline">{{ $contract->parent->number }} — {{ $contract->parent->title }}</a></p>
                        @endif
                        @foreach($contract->addenda as $aditivo)
                            <p class="text-sm"><a href="{{ route('signature-contracts.show', $aditivo) }}" class="font-bold text-grena-ink hover:underline">{{ $aditivo->number ?? 'Aditivo sem número' }}</a> — {{ $aditivo->statusLabel() }}</p>
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
                @can('create', C::class)
                    <form method="POST" action="{{ route('signature-contracts.save-model', $contract) }}" class="{{ $card }}">
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

                @if($podeGerir && !in_array($contract->status, [C::STATUS_APPROVED, C::STATUS_CANCELED], true))
                    <form method="POST" action="{{ route('signature-contracts.cancel', $contract) }}" onsubmit="return confirm('Cancelar este contrato?')" class="text-right">
                        @csrf
                        <button type="submit" class="text-sm font-bold text-danger hover:underline">Cancelar o contrato</button>
                    </form>
                @endif
            </aside>
        </div>
    </x-page>
</x-app-layout>
