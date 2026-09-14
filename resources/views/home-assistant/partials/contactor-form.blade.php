{{--
    Modal de criar/editar contator.
    Parâmetros:
      $contactor  ?Contactor  null ao criar
--}}
@php
    $modalId = $contactor ? 'contactor-' . $contactor->id : 'contactor-new';
    // Depois de um erro de validação, reabre este modal com o que foi digitado
    $useOld  = old('_modal') === $modalId;
    $name    = $useOld ? old('name') : ($contactor->name ?? '');
    $entity  = $useOld ? old('entity_id') : ($contactor->entity_id ?? '');
@endphp

<x-modal :name="$modalId" :show="$useOld && $errors->any()" maxWidth="md" focusable>
    <form method="POST" action="{{ $contactor ? route('home-assistant.update', $contactor) : route('home-assistant.store') }}">
        @csrf
        @if($contactor) @method('PUT') @endif
        <input type="hidden" name="_modal" value="{{ $modalId }}">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $contactor ? 'Editar contator' : 'Novo contator' }}</h3>
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            @if($useOld && $errors->any())
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-sm text-red-700 dark:text-red-300">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <div>
                <label for="{{ $modalId }}-name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                <input id="{{ $modalId }}-name" type="text" name="name" required maxlength="255" value="{{ $name }}"
                    placeholder="Ex.: Quadra 1 – Refletores"
                    class="w-full border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-red-800 focus:border-red-800">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Como o contator aparece no painel e no dashboard.</p>
            </div>

            <div>
                <label for="{{ $modalId }}-entity" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Entity ID no Home Assistant</label>
                <input id="{{ $modalId }}-entity" type="text" name="entity_id" required maxlength="255" value="{{ $entity }}"
                    placeholder="switch.quadra_1" spellcheck="false" autocomplete="off"
                    class="w-full border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-red-800 focus:border-red-800">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Precisa ser idêntico ao do Home Assistant (Configurações → Entidades). É a chave que ele recebe na resposta.
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700">
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200/60 dark:hover:bg-gray-700 transition">
                Cancelar
            </button>
            <button type="submit" class="px-5 py-2 text-sm font-semibold bg-red-800 hover:bg-red-700 text-white rounded-lg shadow-sm transition">
                {{ $contactor ? 'Salvar alterações' : 'Cadastrar contator' }}
            </button>
        </div>
    </form>
</x-modal>
