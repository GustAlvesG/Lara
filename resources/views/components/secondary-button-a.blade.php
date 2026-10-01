@props(['size' => 'md'])

<a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-line-strong bg-surface font-bold text-ink whitespace-nowrap no-underline transition hover:border-ink-3 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</a>
