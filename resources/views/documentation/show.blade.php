<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Documentação') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-6">

                {{-- Sidebar / índice --}}
                <aside class="lg:w-72 shrink-0">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 lg:sticky lg:top-6">
                        <nav class="space-y-6">
                            @foreach ($tree as $section)
                                @if (! empty($section['items']))
                                    <div>
                                        <h3 class="px-2 mb-2 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            {{ $section['label'] }}
                                        </h3>
                                        <ul class="space-y-1">
                                            @foreach ($section['items'] as $item)
                                                @php $active = $item['slug'] === $currentSlug; @endphp
                                                <li>
                                                    <a href="{{ route('docs.show', $item['slug']) }}"
                                                       class="block px-2 py-1.5 rounded-md text-sm transition
                                                              {{ $active
                                                                 ? 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-400 font-semibold'
                                                                 : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                                        {{ $item['title'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </nav>
                    </div>
                </aside>

                {{-- Conteúdo --}}
                <main class="flex-1 min-w-0">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 sm:p-8">
                        <article class="markdown-body">
                            {!! $html !!}
                        </article>
                    </div>
                </main>

            </div>
        </div>
    </div>

    @push('styles')
    @endpush

    <style>
        .markdown-body { color: #1f2937; line-height: 1.7; word-wrap: break-word; }
        .dark .markdown-body { color: #e5e7eb; }
        .markdown-body h1, .markdown-body h2, .markdown-body h3,
        .markdown-body h4 { font-weight: 700; line-height: 1.25; margin: 1.5em 0 0.6em; color: #111827; }
        .dark .markdown-body h1, .dark .markdown-body h2,
        .dark .markdown-body h3, .dark .markdown-body h4 { color: #f9fafb; }
        .markdown-body h1 { font-size: 1.875rem; padding-bottom: .3em; border-bottom: 1px solid #e5e7eb; }
        .markdown-body h2 { font-size: 1.5rem; padding-bottom: .3em; border-bottom: 1px solid #f3f4f6; }
        .markdown-body h3 { font-size: 1.25rem; }
        .markdown-body h4 { font-size: 1.05rem; }
        .dark .markdown-body h1 { border-bottom-color: #374151; }
        .dark .markdown-body h2 { border-bottom-color: #1f2937; }
        .markdown-body p { margin: 0.8em 0; }
        .markdown-body a { color: #b91c1c; text-decoration: underline; }
        .dark .markdown-body a { color: #f87171; }
        .markdown-body ul, .markdown-body ol { margin: 0.8em 0; padding-left: 1.5em; }
        .markdown-body ul { list-style: disc; }
        .markdown-body ol { list-style: decimal; }
        .markdown-body li { margin: 0.3em 0; }
        .markdown-body code {
            background: #f3f4f6; color: #b91c1c; padding: .15em .4em;
            border-radius: .25rem; font-size: .875em;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .dark .markdown-body code { background: #374151; color: #fca5a5; }
        .markdown-body pre {
            background: #1f2937; color: #f9fafb; padding: 1em; border-radius: .5rem;
            overflow-x: auto; margin: 1em 0;
        }
        .markdown-body pre code { background: transparent; color: inherit; padding: 0; }
        .markdown-body blockquote {
            border-left: 4px solid #fca5a5; background: #fef2f2; color: #4b5563;
            padding: .5em 1em; margin: 1em 0; border-radius: 0 .25rem .25rem 0;
        }
        .dark .markdown-body blockquote { background: #7f1d1d33; color: #d1d5db; }
        .markdown-body table { width: 100%; border-collapse: collapse; margin: 1em 0; display: block; overflow-x: auto; }
        .markdown-body th, .markdown-body td { border: 1px solid #e5e7eb; padding: .5em .75em; text-align: left; }
        .dark .markdown-body th, .dark .markdown-body td { border-color: #374151; }
        .markdown-body th { background: #f9fafb; font-weight: 600; }
        .dark .markdown-body th { background: #111827; }
        .markdown-body tr:nth-child(even) td { background: #fafafa; }
        .dark .markdown-body tr:nth-child(even) td { background: #1f2937; }
        .markdown-body hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5em 0; }
        .dark .markdown-body hr { border-top-color: #374151; }
        .markdown-body img { max-width: 100%; height: auto; }
    </style>
</x-app-layout>
