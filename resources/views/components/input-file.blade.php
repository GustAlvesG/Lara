@props(['disabled' => false])

<input type="file" {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-xl border border-line-strong bg-surface text-sm text-ink-2 shadow-none focus:border-grena focus:ring-4 focus:ring-grena-tint file:mr-3 file:h-10 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-grena-tint file:px-4 file:font-bold file:text-grena-ink']) !!}>
