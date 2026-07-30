@php
    $authorName = 'Gustavo Delgado Alves Gonçalves';
    $authorLinkedin = 'https://www.linkedin.com/in/gustavo-alves-81895321a/';
@endphp

<footer class="mt-10 border-t border-gray-200 py-6 dark:border-gray-700">
    <div class="max-w-7xl mx-auto flex flex-col items-center gap-2 px-4 text-center text-sm text-gray-500 sm:flex-row sm:justify-between sm:gap-4 sm:text-left dark:text-gray-400">
        <p>
            &copy; {{ date('Y') }} {{ config('app.name', 'Lara Clube') }} &mdash; Todos os direitos reservados.
        </p>

        <p class="flex items-center gap-2">
            <span>Desenvolvido por {{ $authorName }}</span>
            <a
                href="{{ $authorLinkedin }}"
                target="_blank"
                rel="noopener noreferrer"
                title="LinkedIn de {{ $authorName }}"
                class="inline-flex items-center gap-1 text-gray-500 transition hover:text-[#0A66C2] dark:text-gray-400 dark:hover:text-[#0A66C2]"
            >
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.63-1.85 3.36-1.85 3.59 0 4.25 2.36 4.25 5.44v6.3ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13Zm1.78 13.02H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.55C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.72C24 .77 23.2 0 22.22 0Z"/>
                </svg>
                <span class="sr-only">LinkedIn</span>
            </a>
        </p>
    </div>
</footer>
