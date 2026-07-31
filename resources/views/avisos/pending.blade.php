<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                <svg class="w-5 h-5 text-red-700 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </span>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Aviso de leitura obrigatória
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($restantes > 1)
                        {{ $restantes }} avisos aguardando sua confirmação
                    @else
                        Confirme a leitura para continuar usando o sistema
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden border-t-4 border-red-700">

                @if($aviso->image)
                    <img src="{{ asset('images/avisos/' . $aviso->image) }}"
                         alt="{{ $aviso->title }}"
                         class="w-full max-h-64 object-cover">
                @endif

                <div class="p-6 space-y-4">

                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $aviso->title }}
                    </h1>

                    @if($aviso->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($aviso->tags as $tag)
                                <span class="px-2.5 py-1 text-xs rounded-full bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                    #{{ $tag->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if($aviso->content)
                        <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed aviso-content">
                            {!! $aviso->content !!}
                        </div>
                    @endif

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500">
                        Publicado por
                        <span class="font-medium text-gray-600 dark:text-gray-400">{{ $aviso->creator->name ?? '—' }}</span>
                        em {{ $aviso->created_at->format('d/m/Y \à\s H:i') }}
                    </div>
                </div>

                {{-- Confirmação de ciência --}}
                <div class="bg-gray-50 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700 p-6"
                     x-data="{ ciente: false }">
                    <form action="{{ route('avisos.acknowledge', $aviso) }}" method="POST" class="space-y-4">
                        @csrf

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="confirm" value="1" x-model="ciente"
                                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-red-700 focus:ring-red-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Li e estou ciente do aviso
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $aviso->title }}</span>
                            </span>
                        </label>

                        @error('confirm')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                A confirmação fica registrada com data, hora e seu usuário.
                            </p>
                            <button type="submit" :disabled="!ciente"
                                    class="px-6 py-2 text-sm font-medium text-white bg-red-800 hover:bg-red-700 rounded-lg transition
                                           disabled:opacity-40 disabled:cursor-not-allowed">
                                @if($restantes > 1)
                                    Confirmar e ver próximo
                                @else
                                    Confirmar ciência
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
                As demais telas do sistema ficam disponíveis depois da confirmação.
            </p>

        </div>
    </div>

    <x-slot name="css">
        <style>
            .aviso-content b, .aviso-content strong { font-weight: 700; }
            .aviso-content i, .aviso-content em { font-style: italic; }
            .aviso-content u { text-decoration: underline; }
        </style>
    </x-slot>
</x-app-layout>
