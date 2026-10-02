@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full h-11 px-3.5 rounded-xl border border-line-strong bg-surface text-ink placeholder:text-ink-3 shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint disabled:bg-subtle disabled:text-ink-3']) !!}>
