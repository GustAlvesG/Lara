@php $vehicle = $vehicle ?? null; @endphp

<div class="space-y-4">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome *</label>
        <input type="text" name="name" id="name" required
               value="{{ old('name', $vehicle->name ?? '') }}"
               placeholder="Celta"
               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            É por este nome que a portaria identifica o carro na API — sem acento e em qualquer caixa também funciona.
        </p>
    </div>

    <div>
        <label for="plate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Placa</label>
        <input type="text" name="plate" id="plate"
               value="{{ old('plate', $vehicle->plate ?? '') }}"
               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Opcional. Cadastrada, ela também resolve o veículo na API.</p>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição</label>
        <input type="text" name="description" id="description"
               value="{{ old('description', $vehicle->description ?? '') }}"
               placeholder="Chevrolet Celta 2012, branco"
               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
    </div>

    <div>
        <label for="current_odometer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quilometragem atual</label>
        <input type="number" name="current_odometer" id="current_odometer" min="0"
               value="{{ old('current_odometer', $vehicle->current_odometer ?? '') }}"
               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Só o ponto de partida: a partir da primeira viagem quem atualiza este número são os registros de saída e retorno.
        </p>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1" @checked(old('active', $vehicle->active ?? true))
               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
        Ativo (aparece para a portaria)
    </label>
</div>
