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

        <div class="flex items-center justify-between px-6 py-4 border-b border-line">
            <h3 class="text-lg font-bold text-ink">{{ $contactor ? 'Editar contator' : 'Novo contator' }}</h3>
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')" class="p-1 rounded-lg text-ink-3 hover:text-ink-2" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            @if($useOld && $errors->any())
                <div class="p-3 rounded-lg bg-danger-soft text-sm text-danger">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <div>
                <label for="{{ $modalId }}-name" class="block text-sm font-semibold text-ink mb-1">Nome</label>
                <input id="{{ $modalId }}-name" type="text" name="name" required maxlength="255" value="{{ $name }}"
                    placeholder="Ex.: Quadra 1 – Refletores"
                    class="w-full border-line-strong rounded-lg text-sm bg-surface text-ink focus:ring-grena-tint focus:border-grena">
                <p class="mt-1 text-xs text-ink-2">Como o contator aparece no painel e no dashboard.</p>
            </div>

            <div>
                <label for="{{ $modalId }}-entity" class="block text-sm font-semibold text-ink mb-1">Entity ID no Home Assistant</label>
                <input id="{{ $modalId }}-entity" type="text" name="entity_id" required maxlength="255" value="{{ $entity }}"
                    placeholder="switch.quadra_1" spellcheck="false" autocomplete="off"
                    class="w-full border-line-strong rounded-lg text-sm font-mono bg-surface text-ink focus:ring-grena-tint focus:border-grena">
                <p class="mt-1 text-xs text-ink-2">
                    Precisa ser idêntico ao do Home Assistant (Configurações → Entidades). É a chave que ele recebe na resposta.
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 bg-subtle border-t border-line">
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                class="px-4 py-2 text-sm font-medium text-ink rounded-lg hover:bg-subtle transition">
                Cancelar
            </button>
            <button type="submit" class="px-5 py-2 text-sm font-semibold bg-grena hover:bg-grena-hover text-white rounded-lg shadow-card transition">
                {{ $contactor ? 'Salvar alterações' : 'Cadastrar contator' }}
            </button>
        </div>
    </form>
</x-modal>
