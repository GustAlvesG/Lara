<div>
    @php
        $days = [
            ['value' => 1, 'name_pt' => 'domingo', 'label' => 'Dom', 'for' => 'sunday'],
            ['value' => 2, 'name_pt' => 'segunda-feira', 'label' => 'Seg', 'for' => 'monday'],
            ['value' => 3, 'name_pt' => 'terça-feira', 'label' => 'Ter', 'for' => 'tuesday'],
            ['value' => 4, 'name_pt' => 'quarta-feira', 'label' => 'Qua', 'for' => 'wednesday'],
            ['value' => 5, 'name_pt' => 'quinta-feira', 'label' => 'Qui', 'for' => 'thursday'],
            ['value' => 6, 'name_pt' => 'sexta-feira', 'label' => 'Sex', 'for' => 'friday'],
            ['value' => 7, 'name_pt' => 'sábado', 'label' => 'Sab', 'for' => 'saturday'],
        ];
    @endphp

    @foreach($days as $day)
        <input
            class="form-check week-day-range mx-2"
            data-pt-day="{{ $day['name_pt'] }}"
            type="checkbox"
            name="weekdays[]"
            value="{{ $day['value'] }}"
            id="weekday-{{ $day['value'] }}"
            @if(isset($weekdays) && $weekdays->contains('name_pt', $day['name_pt'])) checked @endif
        >
        <label class="form-check text-gray-900 dark:text-gray-100" for="weekday-{{ $day['value'] }}">
            {{ $day['label'] }}
        </label>
    @endforeach
</div>