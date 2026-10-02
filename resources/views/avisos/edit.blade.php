<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="'Editar: ' . $aviso->title" :back="route('avisos.show', $aviso)" />

        <div class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            <form action="{{ route('avisos.update', $aviso) }}" method="POST" enctype="multipart/form-data" id="aviso-form">
                @csrf @method('PUT')
                @include('avisos.partials.form', ['aviso' => $aviso])

                {{-- Remover imagem existente --}}
                @if ($aviso->image)
                    <div class="mt-4 flex items-center gap-3 rounded-2xl bg-subtle p-3">
                        <img src="{{ asset('images/avisos/' . $aviso->image) }}" class="h-16 w-24 rounded-xl object-cover" alt="Imagem atual">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-2">
                            <input type="checkbox" name="remove_image" value="1" class="rounded border-line-strong text-danger focus:ring-danger-soft">
                            Remover imagem atual
                        </label>
                    </div>
                @endif
            </form>

            {{-- Form de exclusão separado (fora do form de edição) --}}
            <form id="aviso-delete-form"
                  action="{{ route('avisos.destroy', $aviso) }}" method="POST"
                  onsubmit="return confirm('Remover este aviso permanentemente?')">
                @csrf @method('DELETE')
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                <x-danger-button type="submit" form="aviso-delete-form"><x-icon name="trash" /> Remover aviso</x-danger-button>

                <div class="flex flex-wrap gap-2.5">
                    <x-secondary-button-a href="{{ route('avisos.show', $aviso) }}">Cancelar</x-secondary-button-a>
                    <x-primary-button type="submit" form="aviso-form"><x-icon name="check" /> Salvar</x-primary-button>
                </div>
            </div>
        </div>
    </x-page>

    @include('avisos.partials.editor-scripts')
</x-app-layout>
