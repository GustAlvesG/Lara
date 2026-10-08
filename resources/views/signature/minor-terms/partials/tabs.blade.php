{{--
    Abas do Termo de Menores: cada uma aparece para quem tem a permissão dela.
    @include('signature.minor-terms.partials.tabs', ['active' => 'history'])
--}}
@php
    use App\Authorization\Permissions as P;

    $abas = array_filter([
        auth()->user()->can(P::ASSINATURA_TERMO_MENORES_HISTORICO) ? ['history', 'Histórico', route('minor-terms.history')] : null,
        auth()->user()->can(P::ASSINATURA_TERMO_MENORES_GERENCIAR) ? ['terms', 'Termos dos eventos', route('minor-terms.terms')] : null,
        auth()->user()->can(P::ASSINATURA_TERMO_MENORES_PAREAR) ? ['devices', 'Tablet', route('minor-terms.devices')] : null,
    ]);
@endphp
@if(count($abas) > 1)
    <nav class="flex flex-wrap gap-2" aria-label="Termo de Menores">
        @foreach($abas as [$chave, $rotulo, $url])
            <a href="{{ $url }}"
               @if($active === $chave) aria-current="page" @endif
               class="inline-flex h-9 items-center rounded-full px-4 text-sm font-bold no-underline transition {{ $active === $chave ? 'bg-grena text-white' : 'border border-line-strong bg-surface text-ink-2 hover:text-ink' }}">
                {{ $rotulo }}
            </a>
        @endforeach
    </nav>
@endif
