<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <form action="{{ route('freelancer-functions.update', $function) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('freelancer-functions.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                    <div>
                        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $function->name }}</h1>
                        <p class="text-ink-2 font-medium">Edite os dados da função.</p>
                        <p class="text-xs text-ink-3 mt-1">
                            @if($function->createdBy)Cadastrado por {{ $function->createdBy->name }}@endif
                            @if($function->updatedBy && $function->updated_by !== $function->created_by) · Atualizado por {{ $function->updatedBy->name }}@endif
                        </p>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>

            @include('freelancer.functions.partials.form')
        </form>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('freelancer-functions.destroy', $function) }}"
                  onsubmit="return confirm('Excluir a função \'{{ $function->name }}\'?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Função
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
