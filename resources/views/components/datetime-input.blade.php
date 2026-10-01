@props(['disabled' => false])

<input type="date" {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'h-11 px-3.5 rounded-xl border border-line-strong bg-surface text-ink font-mono shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint disabled:bg-subtle disabled:text-ink-3']) !!}>
