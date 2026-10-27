{{--
    Revisão automática pelo Claude, na tela do contrato (só a Gerência).
    Mostra a última revisão — avisando se o texto mudou depois — e, durante a
    validação, leva cada sugestão para a observação da cláusula com um clique.
--}}
@php
    use App\Models\SignatureContractAiReview as R;

    $clausulas = array_values($contract->content['clauses'] ?? []);
    $podePedir = auth()->user()->can('aiReview', $contract);
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
        O texto do contrato, com as partes, é enviado à Anthropic (empresa do Claude) para a análise.
    </p>

    @if($podePedir && !($aiReview && $aiReviewCurrent))
        <form method="POST" action="{{ route('signature-contracts.ai-review', $contract) }}" data-ai-form>
            @csrf
            <x-secondary-button type="submit" size="sm">{{ $aiReview ? 'Analisar o texto atual' : 'Analisar com o Claude' }}</x-secondary-button>
            <span class="ml-2 hidden text-xs text-ink-2" data-ai-wait>Analisando… pode levar até um minuto.</span>
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
