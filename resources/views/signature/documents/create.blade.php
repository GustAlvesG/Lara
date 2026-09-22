<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Novo Documento') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        @if(!$template)
            {{-- Passo 1: escolher o modelo. Só depois o formulário sabe quais dados pedir. --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Escolha o modelo</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    O modelo define o texto do documento, quais dados são preenchidos e como a identidade é conferida no tablet.
                </p>

                @if($templates->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nenhum modelo ativo. Cadastre um em <a href="{{ route('signature-templates.index') }}" class="text-[#A00001] font-bold hover:underline">Modelos</a>.
                    </p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($templates as $modelo)
                            <a href="{{ route('signature-documents.create', ['template' => $modelo->id]) }}"
                               class="block rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:border-[#A00001] hover:shadow-lg transition">
                                <div class="font-bold text-gray-900 dark:text-white">{{ $modelo->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $modelo->description }}</div>
                                <div class="text-[11px] text-gray-400 mt-2">
                                    versão {{ $modelo->version }}
                                    · {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$modelo->identity_check] ?? '' }}
                                    @if($modelo->requires_photo) · com foto @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Modelo</p>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $template->name }} <span class="text-gray-400">v{{ $template->version }}</span></p>
                </div>
                <a href="{{ route('signature-documents.create') }}" class="text-xs text-gray-500 hover:underline">Trocar modelo</a>
            </div>

            <form action="{{ route('signature-documents.store') }}" method="POST">
                @csrf
                <input type="hidden" name="signature_template_id" value="{{ $template->id }}">

                @include('signature.documents.partials.form', ['document' => null, 'template' => $template])

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('signature-documents.index') }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                    <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">Criar rascunho</button>
                </div>
            </form>
        @endif
    </div>
</div>
</x-app-layout>
