@props(['size' => 'md'])

{{-- Confirmação positiva (liberar, aprovar). --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-transparent bg-ok font-bold text-white dark:text-canvas whitespace-nowrap transition hover:opacity-90 hover:-translate-y-px active:translate-y-0 active:scale-[.98] motion-reduce:transform-none focus:outline-none focus-visible:ring-4 focus-visible:ring-ok-soft disabled:cursor-not-allowed disabled:opacity-50 ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</button>
