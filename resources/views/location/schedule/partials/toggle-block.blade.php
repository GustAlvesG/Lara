{{-- filepath: resources/views/location/schedule/partials/_toggle-block.blade.php --}}
@props([
    'checkboxName',
    'checkboxId',
    'checked' => false,
    'label',
    'blockId',
    'fields' => [],
])

<div class="form-check py-4">
    <div class="row">
        <div class="col-3">
            <input class="form-check rule-checkbox" type="checkbox" name="{{ $checkboxName }}" id="{{ $checkboxId }}"
                @if($checked) checked @endif>
            <label class="form-check text-gray-900 dark:text-gray-100" for="{{ $checkboxId }}">
                {{ $label }}
            </label>
        </div>
        <div class="col-9
            @if(!$checked) hidden @endif
            onCheck" id="{{ $blockId }}">
            <div class="row">
                @foreach($fields as $field)
                    @if(isset($field['custom']) && $field['custom'] && isset($field['view']))
                        @include($field['view'], $field['data'] ?? [])
                    @else
                        <div class="col">
                            @if(isset($field['label']))
                                <x-input-label class="{{ $blockId }}-label" for="{{ $field['name'] }}">{{ $field['label'] }}</x-input-label>
                            @endif
                            <x-text-input
                                type="{{ $field['type'] ?? 'text' }}"
                                name="{{ $field['name'] }}"
                                id="{{ $field['name'] }}"
                                class="form-control"
                                value="{{ $field['value'] ?? null }}"
                            />
                           
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>