@props(['size' => 'md'])

<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-line-strong bg-surface font-bold text-ink whitespace-nowrap transition hover:border-ink-3 hover:bg-subtle hover:-translate-y-px active:translate-y-0 active:scale-[.98] motion-reduce:transform-none focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint disabled:cursor-not-allowed disabled:opacity-50 ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</button>
