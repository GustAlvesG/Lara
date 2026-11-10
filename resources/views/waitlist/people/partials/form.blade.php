{{--
    Campos do não sócio, no cadastro e na ficha. O CPF já chega conferido na
    inclusão (passo do CPF) e fica editável na ficha para corrigir digitação.
--}}
@php
    $person = $person ?? null;
    $hint = 'mt-1.5 text-xs text-ink-3';
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="cpf" class="mb-1.5">CPF <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="cpf" id="cpf" required inputmode="numeric" maxlength="14" class="font-mono" placeholder="000.000.000-00"
            value="{{ old('cpf', \App\Support\Cpf::format($person->cpf ?? $cpf ?? '')) }}" />
        <p class="{{ $hint }}">Obrigatório também para menor: é ele que identifica a pessoa em todas as filas.</p>
        <x-input-error :messages="$errors->get('cpf')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="birth_date" value="Data de nascimento" class="mb-1.5" />
        <x-text-input type="date" name="birth_date" id="birth_date" class="sm:max-w-xs"
            value="{{ old('birth_date', $person?->birth_date?->format('Y-m-d')) }}" />
        <x-input-error :messages="$errors->get('birth_date')" class="mt-1" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="name" class="mb-1.5">Nome completo <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="name" id="name" required maxlength="255" value="{{ old('name', $person->name ?? '') }}" />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="phone" class="mb-1.5">Telefone <span class="text-danger">*</span></x-input-label>
        <x-text-input type="tel" name="phone" id="phone" required maxlength="20" value="{{ old('phone', $person->phone ?? '') }}" placeholder="(21) 99999-0000" />
        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="phone_alt" value="Outro telefone" class="mb-1.5" />
        <x-text-input type="tel" name="phone_alt" id="phone_alt" maxlength="20" value="{{ old('phone_alt', $person->phone_alt ?? '') }}" />
        <x-input-error :messages="$errors->get('phone_alt')" class="mt-1" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="email" value="E-mail" class="mb-1.5" />
        <x-text-input type="email" name="email" id="email" maxlength="255" value="{{ old('email', $person->email ?? '') }}" />
        <x-input-error :messages="$errors->get('email')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="guardian_name" value="Responsável (se for menor)" class="mb-1.5" />
        <x-text-input type="text" name="guardian_name" id="guardian_name" maxlength="255" value="{{ old('guardian_name', $person->guardian_name ?? '') }}" />
        <x-input-error :messages="$errors->get('guardian_name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="guardian_phone" value="Telefone do responsável" class="mb-1.5" />
        <x-text-input type="tel" name="guardian_phone" id="guardian_phone" maxlength="20" value="{{ old('guardian_phone', $person->guardian_phone ?? '') }}" />
        <x-input-error :messages="$errors->get('guardian_phone')" class="mt-1" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="notes" value="Observação" class="mb-1.5" />
        <textarea name="notes" id="notes" rows="3" maxlength="2000"
            class="w-full rounded-2xl border border-line-strong bg-surface px-4 py-3 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint">{{ old('notes', $person->notes ?? '') }}</textarea>
        <p class="{{ $hint }}">Ex.: melhor horário para ligar, preferência de turma.</p>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>
</div>
