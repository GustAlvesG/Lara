<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('avisos.show', $aviso) }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Aviso
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">

                <form action="{{ route('avisos.update', $aviso) }}" method="POST" enctype="multipart/form-data" id="aviso-form">
                    @csrf @method('PUT')
                    @include('avisos.partials.form', ['aviso' => $aviso])

                    {{-- Remover imagem existente --}}
                    @if($aviso->image)
                        <div class="mt-4 flex items-center gap-3">
                            <img src="{{ asset('images/avisos/' . $aviso->image) }}" class="h-16 w-24 object-cover rounded" alt="Imagem atual">
                            <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 cursor-pointer">
                                <input type="checkbox" name="remove_image" value="1" class="rounded">
                                Remover imagem atual
                            </label>
                        </div>
                    @endif

                    <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <form action="{{ route('avisos.destroy', $aviso) }}" method="POST"
                              onsubmit="return confirm('Remover este aviso permanentemente?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 dark:hover:text-red-400">
                                Remover aviso
                            </button>
                        </form>

                        <div class="flex gap-3">
                            <a href="{{ route('avisos.show', $aviso) }}"
                               class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                Cancelar
                            </a>
                            <button type="submit" form="aviso-form"
                                class="px-6 py-2 text-sm font-medium text-white bg-red-800 hover:bg-red-700 rounded-lg transition">
                                Salvar
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    @include('avisos.partials.editor-scripts')
</x-app-layout>
