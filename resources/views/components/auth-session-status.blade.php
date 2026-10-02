@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-ok']) }}>
        {{ $status }}
    </div>
@endif
