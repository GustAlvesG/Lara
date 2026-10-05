@php
    $vehicle = $vehicle ?? null;
    $hint = 'mt-1.5 text-xs text-ink-3';
@endphp

<div class="flex flex-col gap-5">
    <div>
        <x-input-label for="name" class="mb-1.5">Nome <span class="text-danger">*</span></x-input-label>
        <x-text-input type="text" name="name" id="name" required value="{{ old('name', $vehicle->name ?? '') }}" placeholder="Celta" />
        <p class="{{ $hint }}">É por este nome que a portaria identifica o carro na API — sem acento e em qualquer caixa também funciona.</p>
    </div>

    <div>
        <x-input-label for="plate" value="Placa" class="mb-1.5" />
        <x-text-input type="text" name="plate" id="plate" value="{{ old('plate', $vehicle->plate ?? '') }}" class="font-mono uppercase tracking-widest" />
        <p class="{{ $hint }}">Opcional. Cadastrada, ela também resolve o veículo na API.</p>
    </div>

    <div>
        <x-input-label for="description" value="Descrição" class="mb-1.5" />
        <x-text-input type="text" name="description" id="description" value="{{ old('description', $vehicle->description ?? '') }}" placeholder="Chevrolet Celta 2012, branco" />
    </div>

    <div>
        <x-input-label for="current_odometer" value="Quilometragem atual" class="mb-1.5" />
        <x-text-input type="number" name="current_odometer" id="current_odometer" min="0" value="{{ old('current_odometer', $vehicle->current_odometer ?? '') }}" class="font-mono sm:max-w-xs" />
        <p class="{{ $hint }}">Só o ponto de partida: a partir da primeira viagem quem atualiza este número são os registros de saída e retorno.</p>
    </div>

    <label class="flex items-center gap-2 text-sm font-semibold text-ink">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" @checked(old('active', $vehicle->active ?? true))
               class="rounded border-line-strong text-grena focus:ring-grena-tint">
        Ativo (aparece para a portaria)
    </label>
</div>
