{{--
    Alternância entre as duas listas da Fila de Espera: por turma e por pessoa.
    $current: 'turmas' | 'pessoas'
--}}
@php
    $tabs = [
        'turmas' => ['Turmas', route('waitlist.index'), 'class'],
        'pessoas' => ['Pessoas', route('waitlist.people.index'), 'users'],
    ];
@endphp

<nav class="flex flex-wrap gap-2" aria-label="Listas da fila de espera">
    @foreach ($tabs as $key => [$label, $url, $glyph])
        <a href="{{ $url }}" @if ($current === $key) aria-current="page" @endif
           class="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-bold transition {{ $current === $key ? 'bg-grena text-white' : 'border border-line-strong bg-surface text-ink-2 hover:text-ink' }}">
            <x-icon :name="$glyph" class="h-4 w-4" /> {{ $label }}
        </a>
    @endforeach
</nav>
