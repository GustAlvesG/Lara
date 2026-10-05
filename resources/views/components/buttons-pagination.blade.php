<button {{ $attributes->merge(['type' => 'submit', 'class' => 'relative inline-flex h-9 min-w-9 items-center justify-center bg-surface px-3 text-sm font-bold text-ink ring-1 ring-inset ring-line-strong transition hover:bg-subtle focus:z-10 focus:outline-none focus:ring-2 focus:ring-grena page-button']) }}>
    {{ $slot }}
</button>
