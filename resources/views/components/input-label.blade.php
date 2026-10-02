@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-bold text-ink']) }}>
    {{ $value ?? $slot }}
</label>
