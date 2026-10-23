{{-- Campos do vínculo torneio × categoria: preço, vagas, montagem dos times,
     formato e sets. `$key` deixa os ids únicos quando há vários formulários na
     tela; `$link` é a categoria ligada sendo editada (ou null). --}}
@php
    use App\Models\Tournament\TournamentCategory as Category;

    $link = $link ?? null;
    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $value = fn (string $field, $default = '') => $link ? $link->{$field} : $default;
@endphp
<div><x-input-label for="price-{{ $key }}" value="Preço por pessoa (R$)" /><x-text-input type="number" step="0.01" min="0" id="price-{{ $key }}" name="price" class="mt-1" :value="$value('price')" required /></div>
<div><x-input-label for="max-{{ $key }}" value="Vagas" /><x-text-input type="number" min="1" id="max-{{ $key }}" name="max_registrations" class="mt-1" :value="$value('max_registrations')" placeholder="Sem limite" /></div>
<div>
    <x-input-label for="formation-{{ $key }}" value="Montagem dos times" />
    <select id="formation-{{ $key }}" name="team_formation" class="{{ $select }}">
        @foreach (Category::FORMATIONS as $option => $label)
            <option value="{{ $option }}" @selected($value('team_formation', Category::FORMATION_ORGANIZER) === $option)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div>
    <x-input-label for="format-{{ $key }}" value="Formato" />
    <select id="format-{{ $key }}" name="format" class="{{ $select }}" data-format>
        @foreach (Category::FORMATS as $option => $label)
            <option value="{{ $option }}" @selected($value('format', Category::FORMAT_SINGLE_ELIMINATION) === $option)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div data-groups-only><x-input-label for="groups-{{ $key }}" value="Número de grupos" /><x-text-input type="number" min="1" id="groups-{{ $key }}" name="groups_count" class="mt-1" :value="$value('groups_count')" /></div>
<div data-groups-only><x-input-label for="qualifiers-{{ $key }}" value="Classificados por grupo" /><x-text-input type="number" min="1" id="qualifiers-{{ $key }}" name="qualifiers_per_group" class="mt-1" :value="$value('qualifiers_per_group')" /></div>
<div><x-input-label for="sets-{{ $key }}" value="Sets para vencer" /><x-text-input type="number" min="1" max="5" id="sets-{{ $key }}" name="sets_to_win" class="mt-1" :value="$value('sets_to_win', 2)" required /></div>
<label class="flex items-center gap-2 self-end pb-3 text-sm font-semibold text-ink">
    <input type="hidden" name="third_place_match" value="0">
    <input type="checkbox" name="third_place_match" value="1" @checked($value('third_place_match', false)) class="h-4 w-4 rounded border-line-strong text-grena-ink">
    Disputa de 3º lugar
</label>
