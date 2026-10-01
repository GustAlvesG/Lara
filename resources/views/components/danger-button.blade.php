@props(['size' => 'md'])

{{-- Destrutivo: só contorno, enche de leve no hover. O vermelho fica para erro. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-danger bg-transparent font-bold text-danger whitespace-nowrap transition hover:bg-danger-soft focus:outline-none focus-visible:ring-4 focus-visible:ring-danger-soft disabled:cursor-not-allowed disabled:opacity-50 ' . ($size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm')]) }}>
    {{ $slot }}
</button>
