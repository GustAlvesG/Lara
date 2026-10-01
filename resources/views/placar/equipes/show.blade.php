<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.equipes.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-ok border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    {{ $equipe->nome }}
                    @if($equipe->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-warn-soft text-warn">Criado em campo</span>
                    @endif
                </h1>
            </div>
        </div>

        {{-- Logo --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line p-6">
            <h2 class="text-lg font-bold text-ink mb-4">Logo</h2>
            <div class="flex items-center gap-6">
                @if($equipe->logoUrl())
                    <img src="{{ $equipe->logoUrl() }}" class="w-24 h-24 rounded-xl object-cover border border-line">
                @else
                    <div class="w-24 h-24 rounded-xl bg-subtle flex items-center justify-center text-xs text-ink-3">sem logo</div>
                @endif
                <div class="flex flex-col gap-2">
                    <form action="{{ route('placar.equipes.logo.store', $equipe) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="arquivo" accept="image/jpeg,image/png,image/webp" required class="text-sm">
                        <button type="submit" class="px-4 py-2 bg-grena text-white rounded-full text-sm font-bold hover:bg-grena-hover transition">Enviar</button>
                    </form>
                    @if($equipe->logo_path)
                    <form action="{{ route('placar.equipes.logo.destroy', $equipe) }}" method="POST" onsubmit="return confirm('Remover a logo?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover logo</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <form action="{{ route('placar.equipes.update', $equipe) }}" method="POST">
            @csrf
            @method('PUT')
            @include('placar.equipes.partials.form')

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Salvar Alterações</button>
            </div>
        </form>

        {{-- Times da equipe --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Times</h2>
                <a href="{{ route('placar.times.create') }}" class="text-sm font-bold text-grena-ink hover:underline">+ Novo time</a>
            </div>
            @if($equipe->times->isEmpty())
                <div class="p-6 text-sm text-ink-2">Nenhum time cadastrado para esta equipe.</div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($equipe->times as $time)
                    <li class="p-4 flex items-center justify-between">
                        <span class="text-sm text-ink">{{ $time->nomeExibicaoResolvido() }} <span class="text-xs text-ink-3">({{ $time->modalidade->nome }} - {{ $time->categoria }})</span></span>
                        <a href="{{ route('placar.times.show', $time) }}" class="text-xs font-bold text-grena-ink hover:underline">Ver</a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.equipes.destroy', $equipe) }}"
                  onsubmit="return confirm('Excluir a equipe \'{{ $equipe->nome }}\'?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Equipe
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
