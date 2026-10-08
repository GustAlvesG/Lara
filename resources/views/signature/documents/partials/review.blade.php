{{--
    Aba "Revisão": a revisão interna do documento concluído — ver SignatureReviewService.
    Outra pessoa, que não acompanhou a assinatura, confere os itens do modelo a partir do dia seguinte.
    Recebe do controller $podeRevisar (tem a permissão) e $bloqueioRevisao (por que não agora; null = pode).
--}}
@php
    use App\Models\SignatureReview;

    $itens = $document->template?->declaredReviewItems() ?? [];
    $feitosAntes = (array) old('feitos', []);
@endphp

<div class="bg-surface rounded-card shadow-card p-6 space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold text-ink">Revisão interna</h3>
            <p class="text-sm text-ink-2 mt-1">
                Outra pessoa, que não acompanhou a assinatura, confere se os processos internos deste atendimento
                foram feitos. Disponível a partir de
                <b>{{ $document->reviewAvailableAt()?->format('d/m/Y') }}</b>; a coordenação revisa antes e revisa os
                próprios.
            </p>
        </div>
        <span @class([
            'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold',
            'bg-ok-soft text-ok' => $document->review_status === SignatureReview::RESULT_OK,
            'bg-danger-soft text-danger' => $document->review_status === SignatureReview::RESULT_ISSUES,
            'bg-warn-soft text-warn' => $document->review_status === null,
        ]) data-review-status>{{ $document->reviewStatusLabel() }}</span>
    </div>

    @if($podeRevisar && $bloqueioRevisao === null)
        <form method="POST" action="{{ route('signature-documents.review', $document) }}" class="space-y-4 border-t border-line pt-4" data-review-form>
            @csrf

            @if($itens)
                <fieldset>
                    <legend class="text-sm font-bold text-ink mb-2">O que conferir</legend>
                    <div class="space-y-2">
                        @foreach($itens as $item)
                            <label class="flex items-center gap-2 text-sm text-ink">
                                <input type="checkbox" name="feitos[]" value="{{ $item['key'] }}"
                                       @checked(in_array($item['key'], $feitosAntes, true))
                                       class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                {{ $item['label'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-ink-2">Marque o que foi feito.</p>
                </fieldset>
            @else
                <p class="rounded-xl bg-subtle p-3 text-xs text-ink-2">
                    O modelo deste documento não lista o que conferir: confira os processos internos do atendimento e
                    registre o resultado. (Os itens são definidos no modelo, em "Itens da revisão".)
                </p>
            @endif

            <fieldset>
                <legend class="text-sm font-bold text-ink mb-2">Resultado</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach(SignatureReview::RESULT_LABELS as $valor => $rotulo)
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="radio" name="resultado" value="{{ $valor }}" @checked(old('resultado') === $valor)
                                   class="border-line-strong text-grena-ink focus:ring-grena-tint">
                            {{ $rotulo }}
                        </label>
                    @endforeach
                </div>
                @error('resultado')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="observacao" class="block text-sm font-bold text-ink mb-1">Observação</label>
                <textarea name="observacao" id="observacao" rows="3" maxlength="2000"
                          class="w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint">{{ old('observacao') }}</textarea>
                <p class="mt-1 text-xs text-ink-2">Obrigatória com pendência: diga o que falta fazer.</p>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                Registrar revisão
            </button>
        </form>
    @elseif($podeRevisar)
        <p class="rounded-xl bg-subtle p-3 text-sm text-ink-2" data-review-block>{{ $bloqueioRevisao }}</p>
    @endif

    @if($document->reviews->isNotEmpty())
        <div class="border-t border-line pt-4">
            <h4 class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-3">Revisões</h4>
            <ul class="space-y-4">
                @foreach($document->reviews as $revisao)
                    <li class="text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'font-bold',
                                'text-ok' => $revisao->result === SignatureReview::RESULT_OK,
                                'text-danger' => $revisao->result === SignatureReview::RESULT_ISSUES,
                            ])>{{ $revisao->resultLabel() }}</span>
                            <span class="text-ink-2">
                                — {{ $revisao->reviewed_by_name ?? 'não registrado' }}, {{ $revisao->created_at?->format('d/m/Y H:i') }}
                            </span>
                            @if($revisao->early)
                                <span class="rounded-full bg-subtle px-2 py-0.5 text-[11px] text-ink-2">antes do prazo</span>
                            @endif
                            @if($revisao->own)
                                <span class="rounded-full bg-subtle px-2 py-0.5 text-[11px] text-ink-2">de quem acompanhou</span>
                            @endif
                        </div>
                        @if($revisao->items)
                            <ul class="mt-1 space-y-0.5 text-xs text-ink-2">
                                @foreach($revisao->items as $item)
                                    <li>{{ $item['done'] ? '✓' : '✗' }} {{ $item['label'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if($revisao->notes)
                            <p class="mt-1 whitespace-pre-line text-xs text-ink">{{ $revisao->notes }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif(!$podeRevisar)
        <p class="text-sm text-ink-2">Nenhuma revisão registrada ainda.</p>
    @endif
</div>
