{{--
    O que falta (trava o envio) e o que vale conferir — ContractRenderer::problems.
    O editor redesenha esta lista pelo JavaScript depois de cada prévia.
--}}
<div data-problems>
    @php
        $erros = collect($problems)->where('level', 'error');
        $avisos = collect($problems)->where('level', 'warning');
    @endphp
    @if($erros->isEmpty() && $avisos->isEmpty())
        <p class="rounded-xl bg-ok-soft px-3 py-2 text-sm font-bold text-ok">Nenhum problema encontrado.</p>
    @endif
    @if($erros->isNotEmpty())
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-danger">Falta corrigir ({{ $erros->count() }})</p>
        <ul class="mb-3 space-y-1 text-sm text-ink">
            @foreach($erros as $p)
                <li class="rounded-lg bg-danger-soft px-3 py-1.5">{{ $p['message'] }}</li>
            @endforeach
        </ul>
    @endif
    @if($avisos->isNotEmpty())
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-warn">Vale conferir ({{ $avisos->count() }})</p>
        <ul class="space-y-1 text-sm text-ink">
            @foreach($avisos as $p)
                <li class="rounded-lg bg-warn-soft px-3 py-1.5">{{ $p['message'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
