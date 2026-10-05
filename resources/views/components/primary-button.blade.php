@props(['size' => 'md'])

{{-- Ação principal da tela, em grená. Uma por tela, de preferência. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-transparent bg-grena font-bold text-white whitespace-nowrap transition hover:bg-grena-hover focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint disabled:cursor-not-allowed disabled:opacity-50 ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</button>
