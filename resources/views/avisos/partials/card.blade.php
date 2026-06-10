@php
    $isExpired = $expired ?? $aviso->isExpired();
    $privacyConfig = [
        'pessoa'  => ['label' => 'Pessoal', 'class' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'],
        'setor'   => ['label' => 'Setor',   'class' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'],
        'publico' => ['label' => 'Público', 'class' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'],
    ];
    $privacyInfo = $privacyConfig[$aviso->privacy] ?? $privacyConfig['setor'];
@endphp

<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden flex flex-col {{ $isExpired ? 'opacity-60' : '' }}">

    @if($aviso->image)
        <img src="{{ asset('images/avisos/' . $aviso->image) }}"
             alt="{{ $aviso->title }}"
             class="w-full h-40 object-cover">
    @endif

    <div class="p-4 flex flex-col flex-1 gap-3">

        {{-- Badges --}}
        <div class="flex flex-wrap gap-1">
            <span class="px-2 py-0.5 text-xs rounded-full {{ $privacyInfo['class'] }}">
                {{ $privacyInfo['label'] }}
            </span>

            @if($isExpired)
                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                    Expirado
                </span>
            @elseif($aviso->expiresSoon())
                <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300">
                    Expira em breve
                </span>
            @endif

            @if($aviso->lembretes->isNotEmpty())
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    {{ $aviso->lembretes->count() }} {{ $aviso->lembretes->count() === 1 ? 'lembrete' : 'lembretes' }}
                </span>
            @endif
        </div>

        {{-- Título --}}
        <a href="{{ route('avisos.show', $aviso) }}"
           class="font-semibold text-gray-900 dark:text-gray-100 hover:text-red-800 dark:hover:text-red-400 leading-snug">
            {{ $aviso->title }}
        </a>

        {{-- Preview --}}
        @if($aviso->content)
            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-3">
                {!! strip_tags($aviso->content) !!}
            </p>
        @endif

        {{-- Rodapé --}}
        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs text-gray-400 dark:text-gray-500">
            <span>{{ $aviso->creator->name ?? '—' }} · {{ $aviso->created_at->diffForHumans() }}</span>

            @if(auth()->user()->can('manage avisos') || auth()->user()->hasRole('admin'))
                <div class="flex gap-2">
                    <a href="{{ route('avisos.edit', $aviso) }}"
                       class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">Editar</a>
                    <form action="{{ route('avisos.destroy', $aviso) }}" method="POST"
                          onsubmit="return confirm('Remover este aviso?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400">Remover</button>
                    </form>
                </div>
            @endif
        </div>

    </div>
</div>
