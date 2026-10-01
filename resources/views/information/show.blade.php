<x-app-layout :bootstrap-grid="false">
    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/editor.css') }}">
    </x-slot>

    <x-page>
        <x-page-title :title="$info->name" :back="route('information.index')">
            Versão de {{ $info->created_at?->format('d/m/Y H:i') ?? '—' }}@if ($info->user) por {{ $info->user->name }}@endif

            <x-slot:actions>
                <x-secondary-button-a href="{{ route('information.history', $info->information_id) }}">
                    <x-icon name="history" /> Histórico
                </x-secondary-button-a>

                @can('infoclube.editar')
                    <x-primary-button-a href="{{ route('information.edit', $info->id) }}">
                        <x-icon name="pencil" /> Editar
                    </x-primary-button-a>

                    <form action="{{ route('information.destroy', $info->information_id) }}" method="POST"
                          onsubmit="return confirm('Você tem certeza que deseja apagar essa informação? Essa ação é irreversível.')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit"><x-icon name="trash" /> Excluir</x-danger-button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('information.partials.details', ['info' => $info])
    </x-page>
</x-app-layout>
