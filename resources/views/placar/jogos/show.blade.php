<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.jogos.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-ok border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    {{ $jogo->timeCasa->nomeExibicaoResolvido() }}
                    @if(!is_null($jogo->placar_casa)) <span class="text-ok">{{ $jogo->placar_casa }} x {{ $jogo->placar_fora }}</span> @else x @endif
                    {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                    @if($jogo->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-warn-soft text-warn">Criado em campo</span>
                    @endif
                </h1>
                <p class="text-ink-2 font-medium">
                    {{ $jogo->modalidade->nome }} · {{ $jogo->data_hora->format('d/m/Y H:i') }}
                    @if($jogo->competicao) · {{ $jogo->competicao->nome }} @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('placar.jogos.escalacao.edit', $jogo) }}" class="inline-flex items-center px-5 py-2.5 bg-ink text-canvas rounded-xl font-bold shadow-card hover:bg-ink-2 transition text-sm">
                Escalação
            </a>
            @can('placar.scout')
            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="inline-flex items-center px-5 py-2.5 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition text-sm">
                Súmula
            </a>
            @endcan
        </div>

        <form action="{{ route('placar.jogos.update', $jogo) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Data e hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora', $jogo->data_hora->format('Y-m-d\TH:i')) }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('data_hora')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Status <span class="text-danger">*</span></label>
                        <select name="status" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            @foreach(\App\Models\Placar\Jogo::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $jogo->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-3">Normalmente o Node quem muda isto (iniciar/encerrar) — ajuste manual só em exceção.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Local</label>
                        <input type="text" name="local" value="{{ old('local', $jogo->local) }}"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    </div>
                    @if($jogo->observacoes)
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Observações</label>
                        <pre class="text-xs text-ink-2 whitespace-pre-wrap p-3 bg-subtle rounded-lg">{{ $jogo->observacoes }}</pre>
                    </div>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Salvar Alterações</button>
            </div>
        </form>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.jogos.destroy', $jogo) }}"
                  onsubmit="return confirm('Excluir este jogo?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Jogo
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
