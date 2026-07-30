<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $info->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    InfoClube · versão de {{ $info->created_at?->format('d/m/Y H:i') ?? '—' }}
                    @if ($info->user)
                        por {{ $info->user->name }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-secondary-button-a href="{{ route('information.index') }}">Voltar</x-secondary-button-a>
                <x-secondary-button-a href="{{ route('information.history', $info->information_id) }}">
                    Histórico
                </x-secondary-button-a>

                @can('edit information')
                    <x-primary-button-a href="{{ route('information.edit', $info->id) }}">
                        Editar
                    </x-primary-button-a>
                @endcan

                @can('delete information')
                    <form action="{{ route('information.destroy', $info->information_id) }}" method="POST"
                          onsubmit="return confirm('Você tem certeza que deseja apagar essa informação? Essa ação é irreversível.')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Excluir</x-danger-button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
        <link rel="stylesheet" href="{{ asset('css/information/form.css') }}">
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('information.partials.details', ['info' => $info])
        </div>
    </div>
</x-app-layout>
