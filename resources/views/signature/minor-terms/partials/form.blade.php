{{--
    Campos do termo de um evento (cadastro e edição). Variáveis: $templates
    (MinorTermController::templateOptions), $term (null no cadastro).
--}}
@php
    $campo = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';
    $selecionado = (int) old('signature_template_id', $term?->signature_template_id);
@endphp
<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="mb-1 block text-sm font-bold text-ink">Nome do evento</label>
        <input type="text" name="name" id="name" maxlength="150" required
               value="{{ old('name', $term?->name) }}" placeholder="OKTOBERPET 2026" class="{{ $campo }}">
        <p class="mt-1 text-xs text-ink-2">Aparece no tablet, na tela verde e no histórico.</p>
        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="signature_template_id" class="mb-1 block text-sm font-bold text-ink">Modelo do termo</label>
        <select name="signature_template_id" id="signature_template_id" required class="{{ $campo }}">
            <option value="">Escolha o modelo…</option>
            @foreach($templates as $opcao)
                @php $t = $opcao['template']; @endphp
                <option value="{{ $t->id }}"
                        @selected($selecionado === $t->id || $selecionado === (int) $t->root_id)
                        @disabled($opcao['problems'] !== [])>
                    {{ $t->name }} (v{{ $t->version }}){{ $opcao['problems'] !== [] ? ' — não serve ao autoatendimento' : '' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-ink-2">
            O texto do evento fica no modelo (Assinaturas → Modelos). Os campos que o tablet preenche sozinho, do
            tipo Texto: <span class="font-mono">[[nome_responsavel]]</span>, <span class="font-mono">[[e_mail_responsavel]]</span>,
            <span class="font-mono">[[cpf_responsavel]]</span>, <span class="font-mono">[[rg_responsavel]]</span>,
            <span class="font-mono">[[endereco_responsavel]]</span>, <span class="font-mono">[[nome_menor]]</span>,
            <span class="font-mono">[[idade_menor]]</span>, <span class="font-mono">[[cpf_menor]]</span> e
            <span class="font-mono">[[rg_menor]]</span>. A data vai num campo do tipo "Data da assinatura".
            Revisar o modelo durante o evento vale para os termos seguintes.
        </p>
        @error('signature_template_id')
            @foreach($errors->get('signature_template_id') as $mensagem)
                <p class="mt-1 text-xs text-danger">{{ $mensagem }}</p>
            @endforeach
        @enderror

        @php $comProblema = collect($templates)->filter(fn($o) => $o['problems'] !== []); @endphp
        @if($comProblema->isNotEmpty())
            <details class="mt-2 text-xs text-ink-2">
                <summary class="cursor-pointer font-semibold">Por que alguns modelos estão bloqueados?</summary>
                <ul class="mt-2 space-y-2">
                    @foreach($comProblema as $opcao)
                        <li>
                            <span class="font-semibold text-ink">{{ $opcao['template']->name }}</span>
                            <ul class="ml-4 list-disc">
                                @foreach($opcao['problems'] as $problema)<li>{{ $problema }}</li>@endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <div>
        <label for="starts_on" class="mb-1 block text-sm font-bold text-ink">Disponível a partir de</label>
        <input type="date" name="starts_on" id="starts_on" required
               value="{{ old('starts_on', $term?->starts_on?->toDateString()) }}" class="{{ $campo }}">
        @error('starts_on')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="ends_on" class="mb-1 block text-sm font-bold text-ink">Disponível até</label>
        <input type="date" name="ends_on" id="ends_on" required
               value="{{ old('ends_on', $term?->ends_on?->toDateString()) }}" class="{{ $campo }}">
        <p class="mt-1 text-xs text-ink-2">Dias inteiros: o tablet aceita termos novos do primeiro ao último dia, inclusive.</p>
        @error('ends_on')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>
</div>
