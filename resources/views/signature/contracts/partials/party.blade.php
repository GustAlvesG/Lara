{{--
    Uma parte do contrato (contratada ou fiador). $k = chave do formulário
    (o uid, ou __K__ no molde); $p = os dados. Ver ContractContent::parties.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $n = fn (string $campo) => 'parties[' . $k . '][' . $campo . ']';
@endphp
<div class="ct-row space-y-3 rounded-xl border border-line p-4" data-row="party">
    <input type="hidden" name="{{ $n('uid') }}" value="{{ $p['uid'] ?? '' }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <select name="{{ $n('role') }}" class="{{ $f }} !w-auto" aria-label="Papel">
                @foreach(\App\Services\Signature\Contracts\ContractContent::PARTY_ROLES as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(($p['role'] ?? 'contratada') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <select name="{{ $n('kind') }}" class="{{ $f }} !w-auto" data-party-kind aria-label="Tipo">
                <option value="pj" @selected(($p['kind'] ?? 'pj') === 'pj')>Pessoa jurídica (CNPJ)</option>
                <option value="pf" @selected(($p['kind'] ?? 'pj') === 'pf')>Pessoa física (CPF)</option>
            </select>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir">↑</button>
            <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer">↓</button>
            <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft">Remover</button>
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-2 rounded-lg bg-subtle p-2">
        <div class="min-w-0 flex-1">
            <label class="{{ $l }}">Buscar no cadastro (contratadas e fiadores já usados)</label>
            <input type="search" data-party-search class="{{ $f }}" placeholder="Nome, CNPJ ou CPF">
        </div>
        <div data-party-results class="w-full space-y-1"></div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="{{ $l }}">Termo no contrato</label>
            <input type="text" name="{{ $n('term') }}" value="{{ $p['term'] ?? 'CONTRATADA' }}" class="{{ $f }} uppercase" maxlength="40" placeholder="CONTRATADA, LOCATÁRIA…">
        </div>
        <div data-only="pf" @class(['hidden' => ($p['kind'] ?? 'pj') !== 'pf'])>
            <label class="{{ $l }}">Concordância ("inscrito/inscrita")</label>
            <select name="{{ $n('gender') }}" class="{{ $f }}">
                <option value="m" @selected(($p['gender'] ?? 'm') === 'm')>Masculino</option>
                <option value="f" @selected(($p['gender'] ?? 'm') === 'f')>Feminino</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $l }}">Nome / razão social</label>
            <input type="text" name="{{ $n('name') }}" value="{{ $p['name'] ?? '' }}" class="{{ $f }}" maxlength="200">
        </div>
        <div>
            <label class="{{ $l }}">CNPJ ou CPF</label>
            <input type="text" name="{{ $n('document') }}" value="{{ $p['document'] ?? '' }}" class="{{ $f }}" maxlength="20" inputmode="text">
        </div>
        <div>
            <label class="{{ $l }}">Telefone</label>
            <input type="text" name="{{ $n('phone') }}" value="{{ $p['phone'] ?? '' }}" class="{{ $f }}" maxlength="30">
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $l }}">Endereço (sede ou residência)</label>
            <input type="text" name="{{ $n('address') }}" value="{{ $p['address'] ?? '' }}" class="{{ $f }}" maxlength="300"
                   placeholder="Rua, nº, bairro, cidade/UF, CEP">
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $l }}">Qualificação complementar (opcional)</label>
            <input type="text" name="{{ $n('details') }}" value="{{ $p['details'] ?? '' }}" class="{{ $f }}" maxlength="500"
                   placeholder="Pessoa física: brasileira, casada, empresária, RG nº …   Empresa: Inscrição Estadual nº …">
        </div>
        <div data-only="pj" @class(['hidden' => ($p['kind'] ?? 'pj') === 'pf'])>
            <label class="{{ $l }}">Representante (quem assina pela empresa)</label>
            <input type="text" name="{{ $n('rep_name') }}" value="{{ $p['rep_name'] ?? '' }}" class="{{ $f }}" maxlength="200">
        </div>
        <div data-only="pj" @class(['hidden' => ($p['kind'] ?? 'pj') === 'pf'])>
            <label class="{{ $l }}">CPF do representante</label>
            <input type="text" name="{{ $n('rep_cpf') }}" value="{{ $p['rep_cpf'] ?? '' }}" class="{{ $f }}" maxlength="14" inputmode="numeric">
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $l }}">E-mail</label>
            <input type="email" name="{{ $n('email') }}" value="{{ $p['email'] ?? '' }}" class="{{ $f }}" maxlength="150">
        </div>
    </div>
</div>
