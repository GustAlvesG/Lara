{{--
    Caixas de seleção das turmas ativas, agrupadas por modalidade.
    $classes: coleção de WaitlistClass; $selected: ids já marcados.
--}}
@php $selected = array_map('intval', (array) ($selected ?? [])); @endphp

@if ($classes->isEmpty())
    <p class="text-sm text-ink-3">
        Nenhuma turma ativa disponível.
        <a href="{{ route('waitlist.classes.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar turma</a>.
    </p>
@else
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($classes->groupBy('modality') as $modality => $group)
            <fieldset>
                <legend class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">{{ $modality }}</legend>
                <div class="flex flex-col gap-2">
                    @foreach ($group as $class)
                        <label class="flex items-start gap-2.5 text-sm text-ink">
                            <input type="checkbox" name="classes[]" value="{{ $class->id }}" class="mt-0.5 rounded border-line-strong text-grena focus:ring-grena-tint"
                                @checked(in_array($class->id, $selected, true))>
                            <span>{{ $class->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>
@endif
<x-input-error :messages="array_merge($errors->get('classes'), \Illuminate\Support\Arr::flatten($errors->get('classes.*')))" class="mt-2" />
