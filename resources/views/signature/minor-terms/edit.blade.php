{{--
    Edição do termo de um evento: nome, modelo, vigência e se está ativo.
    Os termos já assinados não mudam — cada um é um documento congelado.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="{{ $term->name }}" :back="route('minor-terms.terms')">
            Os termos já assinados não mudam: cada um é um documento congelado. A alteração vale para os próximos.
        </x-page-title>

        @include('partials.alerts')

        <form action="{{ route('minor-terms.update', $term) }}" method="POST" class="rounded-card bg-surface p-6 shadow-card">
            @csrf
            @method('PUT')

            @include('signature.minor-terms.partials.form', ['term' => $term])

            <label class="mt-5 flex items-center gap-2 text-sm text-ink">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $term->active))
                       class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                Ativo — desmarque para tirar o termo do tablet antes do fim da vigência.
            </label>

            <div class="mt-5 flex justify-end gap-2">
                <x-secondary-button-a href="{{ route('minor-terms.terms') }}">Cancelar</x-secondary-button-a>
                <x-primary-button>Salvar</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
