{{--
    Quem assina. $k = chave; $s = ContractContent::signers. Diretor vem de
    Assinaturas → Diretoria (assina no tablet com matrícula e PIN); pessoa é
    digitada. O presidente não aparece aqui: entra sozinho, pelo clube.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $n = fn (string $campo) => 'signers[' . $k . '][' . $campo . ']';
    $fonte = ($s['source'] ?? 'person') === 'director' ? 'director' : 'person';
    $escolhido = ($s['area_id'] ?? null) && ($s['user_id'] ?? null) ? $s['area_id'] . ':' . $s['user_id'] : '';
@endphp
<div class="ct-row space-y-3 rounded-xl border border-line p-4" data-row="signer">
    <input type="hidden" name="{{ $n('uid') }}" value="{{ $s['uid'] ?? '' }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <select name="{{ $n('party') }}" class="{{ $f }} !w-auto pr-10" aria-label="Assina como">
                @foreach(\App\Services\Signature\Contracts\ContractContent::SIGNER_PARTIES as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(($s['party'] ?? 'contratada') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <select name="{{ $n('source') }}" class="{{ $f }} !w-auto pr-10" data-signer-source aria-label="Quem é">
                <option value="person" @selected($fonte === 'person')>Pessoa (digitar os dados)</option>
                <option value="director" @selected($fonte === 'director')>Diretor do clube</option>
            </select>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" data-move="up" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Subir">↑</button>
            <button type="button" data-move="down" class="rounded-md px-2 py-1 text-xs text-ink-2 hover:bg-subtle" title="Descer">↓</button>
            <button type="button" data-remove class="rounded-md px-2 py-1 text-xs font-bold text-danger hover:bg-danger-soft">Remover</button>
        </div>
    </div>

    <div data-only-source="director" @class(['hidden' => $fonte !== 'director'])>
        <label class="{{ $l }}">Diretor (área — pessoa)</label>
        <select name="{{ $n('director') }}" class="{{ $f }} pr-10">
            <option value="">Escolha…</option>
            @foreach($directors as $area)
                <optgroup label="{{ $area['area'] }}">
                    @foreach($area['holders'] as $holder)
                        <option value="{{ $area['area_id'] }}:{{ $holder['user_id'] }}" @selected($escolhido === $area['area_id'] . ':' . $holder['user_id']) @disabled($holder['blocked'])>
                            {{ $holder['name'] }} ({{ $holder['role_label'] }}){{ $holder['blocked'] ? ' — ' . $holder['blocked'] : '' }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @if($directors === [])
            <p class="mt-1 text-xs text-ink-3">Nenhuma área da diretoria com titular. Cadastre em Assinaturas → Diretoria.</p>
        @endif
        {{-- O nome do diretor vai junto só para a prévia; quem vale é o cadastro (DirectorAreaService::signerData). --}}
        <input type="hidden" name="{{ $n('name') }}" value="{{ $fonte === 'director' ? ($s['name'] ?? '') : '' }}" data-director-name @disabled($fonte !== 'director')>
    </div>

    <div data-only-source="person" @class(['grid grid-cols-1 gap-3 sm:grid-cols-2', 'hidden' => $fonte !== 'person'])>
        <div class="sm:col-span-2">
            <label class="{{ $l }}">Nome</label>
            <input type="text" name="{{ $n('name') }}" data-person-name value="{{ $fonte === 'person' ? ($s['name'] ?? '') : '' }}" class="{{ $f }}" maxlength="200" @disabled($fonte !== 'person')>
        </div>
        <div>
            <label class="{{ $l }}">CPF</label>
            <input type="text" name="{{ $n('cpf') }}" value="{{ $s['cpf'] ?? '' }}" class="{{ $f }}" maxlength="14" inputmode="numeric">
        </div>
        <div>
            <label class="{{ $l }}">E-mail (para o link de assinatura a distância)</label>
            <input type="email" name="{{ $n('email') }}" value="{{ $s['email'] ?? '' }}" class="{{ $f }}" maxlength="150">
        </div>
        <div>
            <label class="{{ $l }}">Telefone</label>
            <input type="text" name="{{ $n('phone') }}" value="{{ $s['phone'] ?? '' }}" class="{{ $f }}" maxlength="30">
        </div>
    </div>

    <div>
        <label class="{{ $l }}">Cargo / qualidade, sai sob o nome (opcional)</label>
        <input type="text" name="{{ $n('caption') }}" value="{{ $s['caption'] ?? '' }}" class="{{ $f }}" maxlength="120" placeholder="Diretor de Patrimônio do CFCSN, Sócia-administradora…">
    </div>
</div>
