{{--
    Cadastro e edição de turma da fila de espera. As modalidades já usadas
    viram sugestão (datalist), para "Natação" não virar também "natacao".
--}}
@php
    $isEdit = $waitlistClass !== null;
    $hint = 'mt-1.5 text-xs text-ink-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="$isEdit ? 'Editar turma' : 'Nova turma'"
            :back="$isEdit ? route('waitlist.classes.show', $waitlistClass) : route('waitlist.index')">
            Turma da fila de espera de não sócios. Não tem ligação com as turmas do MultiClubes.
        </x-page-title>

        @include('partials.alerts')

        <form method="POST" action="{{ $isEdit ? route('waitlist.classes.update', $waitlistClass) : route('waitlist.classes.store') }}"
              class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="modality" class="mb-1.5">Modalidade <span class="text-danger">*</span></x-input-label>
                    <x-text-input type="text" name="modality" id="modality" required maxlength="100" list="modalidades"
                        value="{{ old('modality', $waitlistClass->modality ?? '') }}" placeholder="Ex.: Natação" />
                    <datalist id="modalidades">
                        @foreach ($modalities as $modality)
                            <option value="{{ $modality }}"></option>
                        @endforeach
                    </datalist>
                    <x-input-error :messages="$errors->get('modality')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="name" class="mb-1.5">Turma <span class="text-danger">*</span></x-input-label>
                    <x-text-input type="text" name="name" id="name" required maxlength="150"
                        value="{{ old('name', $waitlistClass->name ?? '') }}" placeholder="Ex.: Infantil ter/qui 9h" />
                    <p class="{{ $hint }}">Dias, horário e faixa etária no nome ajudam a secretaria a escolher a turma certa.</p>
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="notes" value="Observação" class="mb-1.5" />
                    <x-text-input type="text" name="notes" id="notes" maxlength="255" value="{{ old('notes', $waitlistClass->notes ?? '') }}"
                        placeholder="Ex.: professor, local, idade mínima" />
                    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                </div>

                <label class="flex items-start gap-3 sm:col-span-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="mt-1 rounded border-line-strong text-grena focus:ring-grena-tint"
                        @checked(old('active', $waitlistClass->active ?? true))>
                    <span>
                        <span class="font-semibold text-ink">Turma ativa</span>
                        <span class="block text-xs text-ink-3">Turma inativa não recebe gente nova; a fila dela continua visível.</span>
                    </span>
                </label>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ $isEdit ? route('waitlist.classes.show', $waitlistClass) : route('waitlist.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> {{ $isEdit ? 'Salvar' : 'Cadastrar turma' }}</x-primary-button>
            </div>
        </form>

        @if ($isEdit)
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line p-5">
                <p class="text-sm text-ink-2">
                    Só dá para excluir turma que nunca teve ninguém na fila. A que já teve guarda o histórico: desmarque "Turma ativa".
                </p>
                <form method="POST" action="{{ route('waitlist.classes.destroy', $waitlistClass) }}" onsubmit="return confirm('Excluir esta turma?');">
                    @csrf
                    @method('DELETE')
                    <x-danger-button><x-icon name="trash" /> Excluir</x-danger-button>
                </form>
            </section>
        @endif
    </x-page>
</x-app-layout>
