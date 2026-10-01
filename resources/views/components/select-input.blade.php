@props(['disabled' => false])

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full h-11 pl-3.5 pr-9 rounded-xl border border-line-strong bg-surface text-ink shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint disabled:bg-subtle disabled:text-ink-3']) !!}>
    {{ $slot }}
</select>
