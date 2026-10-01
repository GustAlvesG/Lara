{{--
    Busca livre dos históricos de acesso (?q=), dentro do formulário de
    filtros: o mesmo envio leva texto, status e datas juntos. O filtro mora
    em CompanyAccessLog::scopeSearch().
--}}
<div class="mb-4">
    <label for="busca-acessos" class="sr-only">Buscar nos acessos</label>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-3" />
        <input type="search" id="busca-acessos" name="q" value="{{ request('q') }}" autocomplete="off"
               placeholder="{{ $placeholder ?? 'Placa, documento, nome, empresa ou motivo' }}"
               class="h-11 w-full rounded-full border border-line-strong bg-surface pl-10 pr-4 text-ink placeholder:text-ink-3 shadow-none transition focus:border-grena focus:ring-4 focus:ring-grena-tint">
    </div>
</div>
