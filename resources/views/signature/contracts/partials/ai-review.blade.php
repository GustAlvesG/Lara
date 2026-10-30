{{--
    Revisão automática pelo Claude, na tela do contrato e no editor.
    Mostra a última revisão — avisando se o texto mudou depois — e, durante a
    validação, leva cada sugestão para a observação da cláusula com um clique.
    Trechos com valores, CPF, documentos ou endereço ($aiSensitive) só vão
    ao Claude se quem pede marcar.

    No editor ($noEditor), o botão é do formulário do contrato (`form="ctForm"`):
    salva primeiro e analisa o texto salvo, para não perder o que foi digitado.
--}}
@php
    use App\Models\SignatureContractAiReview as R;

    $noEditor = $noEditor ?? false;
    $clausulas = array_values($contract->content['clauses'] ?? []);
    $podePedir = auth()->user()->can('aiReview', $contract);
    $motivos = fn(array $r) => collect($r)->map(fn($m) => R::SENSITIVE_LABELS[$m] ?? $m)->join(', ', ' e ');
@endphp
<div id="revisao-ia" class="space-y-3 rounded-card bg-surface p-5 shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="font-display text-base font-semibold text-ink">Revisão automática (Claude)</p>
        @if($aiReview)
            <x-pill :kind="$aiReview->verdictKind()">{{ $aiReview->verdictLabel() }}</x-pill>
        @endif
    </div>

    @if($aiReview)
        <p class="text-xs text-ink-2">
            Pedida por {{ $aiReview->requested_by_name ?? '—' }} em {{ $aiReview->created_at?->format('d/m/Y H:i') }}.
            @unless($aiReviewCurrent)
                <span class="font-bold text-warn">O texto mudou depois desta revisão.</span>
            @endunless
        </p>
        <p class="whitespace-pre-line text-sm text-ink">{{ $aiReview->summary }}</p>

        @if(!empty($aiReview->withheld))
            <p class="text-xs text-ink-2">
                <b>Não enviados ao Claude</b> (não foram analisados):
                {{ collect($aiReview->withheld)->map(fn($w) => $w['label'] . ' (' . $motivos($w['reasons'] ?? []) . ')')->join('; ') }}.
            </p>
        @endif

        @if($aiReview->findings !== [])
            <div class="space-y-2">
                @foreach($aiReview->findings as $a)
                    @php
                        $clausula = $a['clausula'] > 0 ? ($clausulas[$a['clausula'] - 1] ?? null) : null;
                        $alvo = $clausula ? 'cm-' . $clausula['uid'] : 'note';
                    @endphp
                    <div class="rounded-xl border border-line p-3 text-sm">
                        <p class="text-xs font-bold text-ink-2">
                            <span @class([
                                'rounded-full px-2',
                                'bg-danger-soft text-danger' => $a['gravidade'] === 'alta',
                                'bg-warn-soft text-warn' => $a['gravidade'] === 'media',
                                'bg-subtle text-ink-2' => $a['gravidade'] === 'baixa',
                            ])>{{ ['alta' => 'Grave', 'media' => 'Atenção', 'baixa' => 'Detalhe'][$a['gravidade']] ?? $a['gravidade'] }}</span>
                            @if($clausula)
                                <a href="#ct-{{ $clausula['uid'] }}" class="ml-1 text-grena-ink hover:underline">Cláusula {{ $a['clausula'] }}</a>
                            @endif
                            @if($a['trecho'] !== '') · {{ $a['trecho'] }} @endif
                        </p>
                        <p class="mt-1 text-ink">{{ $a['problema'] }}</p>
                        @if($a['sugestao'] !== '')
                            <p class="mt-1 text-ink-2"><b>Sugestão:</b> {{ $a['sugestao'] }}</p>
                        @endif
                        @if($podeDecidir)
                            <button type="button" class="mt-1 text-xs font-bold text-grena-ink hover:underline"
                                    data-ai-take="{{ $alvo }}"
                                    data-ai-text="{{ $a['problema'] . ($a['sugestao'] !== '' ? ' Sugestão: ' . $a['sugestao'] : '') }}">
                                Levar para a observação {{ $clausula ? 'da cláusula' : 'geral' }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <p class="text-sm text-ink-2">
            O Claude lê o contrato inteiro e aponta inconsistências (partes, valores por extenso, prazos, remissões,
            cláusulas contraditórias ou faltando), com sugestão de alteração.
        </p>
    @endif

    <p class="text-xs text-ink-3">
        Sugestões automáticas: confira antes de usar. Não substituem a leitura nem a conferência do Lara.
        O texto do contrato é enviado à Anthropic (empresa do Claude) para a análise; os trechos com valores, CPF,
        documentos ou endereço só vão se você marcar.
    </p>

    @php $atual = $aiReview && $aiReviewCurrent; @endphp
    @if($noEditor && $podePedir)
        @if($aiSensitive !== [])
            <details class="text-sm">
                <summary class="cursor-pointer text-xs font-bold text-grena-ink">Incluir trechos com dados sensíveis ({{ count($aiSensitive) }})</summary>
                <div class="mt-2 space-y-1 rounded-xl border border-line p-3">
                    @foreach($aiSensitive as $s)
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" form="ctForm" name="include[]" value="{{ $s['key'] }}" class="mt-1 rounded border-line">
                            <span>{{ $s['label'] }} <span class="text-xs text-ink-2">({{ $motivos($s['reasons']) }})</span></span>
                        </label>
                    @endforeach
                </div>
            </details>
        @endif
        <x-secondary-button type="submit" size="sm" form="ctForm" data-ai-editor>Salvar e analisar</x-secondary-button>
        <span class="ml-2 hidden text-xs text-ink-2" data-ai-wait>Analisando… pode levar até um minuto.</span>
    @elseif($podePedir && (!$atual || $aiSensitive !== []))
        <form method="POST" action="{{ route('signature-contracts.ai-review', $contract) }}" data-ai-form>
            @csrf
            @if($atual)
                <details class="text-sm">
                    <summary class="cursor-pointer text-xs font-bold text-grena-ink">Analisar de novo escolhendo outros trechos</summary>
            @endif

            @if($aiSensitive !== [])
                <fieldset class="mb-3 space-y-1 rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-bold text-ink-2">Trechos com dados sensíveis — ficam de fora, a menos que você marque</legend>
                    @foreach($aiSensitive as $s)
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="include[]" value="{{ $s['key'] }}" class="mt-1 rounded border-line">
                            <span>{{ $s['label'] }} <span class="text-xs text-ink-2">({{ $motivos($s['reasons']) }})</span></span>
                        </label>
                    @endforeach
                </fieldset>
            @endif

            <x-secondary-button type="submit" size="sm">{{ $atual ? 'Analisar de novo' : ($aiReview ? 'Analisar o texto atual' : 'Analisar com o Claude') }}</x-secondary-button>
            <span class="ml-2 hidden text-xs text-ink-2" data-ai-wait>Analisando… pode levar até um minuto.</span>

            @if($atual)
                </details>
            @endif
        </form>
    @endif
</div>

<script>
    (() => {
        document.querySelectorAll('[data-ai-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelector('button[type=submit]').disabled = true;
                form.querySelector('[data-ai-wait]').classList.remove('hidden');
            });
        });

        // No editor: quem define o que acontece depois de salvar é o botão clicado.
        document.getElementById('ctForm')?.addEventListener('submit', (e) => {
            if (!e.submitter?.hasAttribute('data-ai-editor')) return;

            e.currentTarget.querySelector('[data-next]').value = 'ai-review';
            setTimeout(() => { e.submitter.disabled = true; });
            document.querySelector('[data-ai-wait]').classList.remove('hidden');
        });

        document.querySelectorAll('[data-ai-take]').forEach((botao) => {
            botao.addEventListener('click', () => {
                const campo = document.getElementById(botao.dataset.aiTake);
                if (!campo) return;

                const detalhes = campo.closest('details');
                if (detalhes) detalhes.open = true;

                campo.value = (campo.value.trim() ? campo.value.trim() + '\n' : '') + botao.dataset.aiText;
                campo.focus();
                campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                botao.textContent = 'Levado ✓';
            });
        });
    })();
</script>
