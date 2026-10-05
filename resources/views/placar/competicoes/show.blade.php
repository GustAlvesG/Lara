<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.competicoes.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-ok border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $competicao->nome }}</h1>
                <p class="text-ink-2 font-medium">{{ $competicao->modalidade->nome }} · {{ $competicao->temporada }}</p>
            </div>
        </div>

        <form action="{{ route('placar.competicoes.update', $competicao) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" value="{{ old('nome', $competicao->nome) }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('nome')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Temporada <span class="text-danger">*</span></label>
                        <input type="number" name="temporada" value="{{ old('temporada', $competicao->temporada) }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('temporada')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 p-4 rounded-xl border border-line cursor-pointer hover:bg-subtle transition">
                            <input type="hidden" name="ativo" value="0">
                            <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $competicao->ativo))
                                class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft">
                            <span class="text-sm font-bold text-ink">Ativa</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Salvar Alterações</button>
            </div>
        </form>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Jogos</h2>
                <a href="{{ route('placar.jogos.create') }}" class="text-sm font-bold text-grena-ink hover:underline">+ Novo jogo</a>
            </div>
            @if($jogos->isEmpty())
                <div class="p-6 text-sm text-ink-2">Nenhum jogo vinculado a esta competição.</div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($jogos as $jogo)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.jogos.show', $jogo) }}" class="text-ink hover:underline">
                            {{ $jogo->timeCasa->nomeExibicaoResolvido() }} x {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                        </a>
                        <span class="text-xs text-ink-3">{{ $jogo->data_hora->format('d/m/Y H:i') }} · {{ $jogo->status }}</span>
                    </li>
                    @endforeach
                </ul>
                <div class="p-4">{{ $jogos->links() }}</div>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.competicoes.destroy', $competicao) }}"
                  onsubmit="return confirm('Excluir esta competição?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Competição
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
