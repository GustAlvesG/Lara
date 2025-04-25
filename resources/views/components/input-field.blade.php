@props(['label', 'type' => 'text', 'name', 'value', 'disabled' => false, 'min' => '', 'max' => '', 'step' => ''])

<div class="form-group py-1">
    <x-input-label for="{{ $name }}">{{ $label }}</x-input-label>
    <x-text-input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" class="form-control" value="{{ $value }}" {{ $disabled ? 'disabled' : '' }} min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"/>
</div>