{{--
    Campos do funcionário, para o cadastro e para a ficha. Rescisão só aparece
    na ficha: quem é cadastrado agora ainda não saiu.
--}}
@php
    $employee = $employee ?? null;
    $hint = 'mt-1.5 text-xs text-ink-3';
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="employee_code" class="mb-1.5">Matrícula <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="employee_code" id="employee_code" required maxlength="20"
            value="{{ old('employee_code', $employee->employee_code ?? '') }}" class="font-mono" />
        <p class="{{ $hint }}">Se um usuário do painel tiver a mesma matrícula, ele é este funcionário e entra no setor dele.</p>
        <x-input-error :messages="$errors->get('employee_code')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="cpf" class="mb-1.5">CPF <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="cpf" id="cpf" required inputmode="numeric" maxlength="14"
            value="{{ old('cpf', $employee ? \App\Support\Cpf::format($employee->cpf) : '') }}" class="font-mono" placeholder="000.000.000-00" />
        <x-input-error :messages="$errors->get('cpf')" class="mt-1" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="name" class="mb-1.5">Nome completo <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="name" id="name" required maxlength="255" value="{{ old('name', $employee->name ?? '') }}" />
        <p class="{{ $hint }}">No pedido de carro de aplicativo, a pessoa é conferida pelo primeiro nome.</p>
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="sector_id" class="mb-1.5">Setor <span class="text-danger">*</span></x-input-label>
        <x-select-input name="sector_id" id="sector_id" required>
            <option value="">Escolha o setor</option>
            @foreach ($sectors as $sectorId => $sectorName)
                <option value="{{ $sectorId }}" @selected((string) old('sector_id', $employee->sector_id ?? '') === (string) $sectorId)>{{ $sectorName }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('sector_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="position" value="Cargo" class="mb-1.5" />
        <x-text-input type="text" name="position" id="position" maxlength="255" value="{{ old('position', $employee->position ?? '') }}" />
        <x-input-error :messages="$errors->get('position')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="admission_date" value="Data de admissão" class="mb-1.5" />
        <x-text-input type="date" name="admission_date" id="admission_date" class="sm:max-w-xs"
            value="{{ old('admission_date', $employee?->admission_date?->format('Y-m-d')) }}" />
        <x-input-error :messages="$errors->get('admission_date')" class="mt-1" />
    </div>

    @if ($employee)
        <div class="sm:col-span-2 grid grid-cols-1 gap-5 border-t border-line pt-5 sm:grid-cols-2">
            <div>
                <x-input-label for="termination_date" value="Rescisão" class="mb-1.5" />
                <x-text-input type="date" name="termination_date" id="termination_date" class="sm:max-w-xs"
                    value="{{ old('termination_date', $employee->termination_date?->format('Y-m-d')) }}" />
                <p class="{{ $hint }}">A partir desta data o funcionário não pede carro de aplicativo nem dirige a frota. Limpar a data limpa o motivo junto.</p>
                <x-input-error :messages="$errors->get('termination_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="termination_reason" value="Motivo da rescisão" class="mb-1.5" />
                <x-text-input type="text" name="termination_reason" id="termination_reason" maxlength="255"
                    value="{{ old('termination_reason', $employee->termination_reason) }}" placeholder="Pedido de demissão, dispensa…" />
                <x-input-error :messages="$errors->get('termination_reason')" class="mt-1" />
            </div>
        </div>
    @endif
</div>
