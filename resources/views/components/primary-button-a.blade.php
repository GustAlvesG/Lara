@props(['size' => 'md'])

<a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-transparent bg-grena font-bold text-white whitespace-nowrap no-underline transition hover:bg-grena-hover hover:-translate-y-px active:translate-y-0 active:scale-[.98] motion-reduce:transform-none focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</a>
