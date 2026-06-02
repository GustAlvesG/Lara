<div class="space-y-5">

    <div>
        <x-input-label for="plate" :value="__('Placa')" />
        <x-text-input name="plate" id="plate" class="block mt-1 w-full uppercase tracking-widest"
            type="text" :value="old('plate', $item->plate ?? '')"
            required placeholder="Ex: ABC1D23" maxlength="20" />
        <x-input-error :messages="$errors->get('plate')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Nome')" />
        <x-text-input name="name" id="name" class="block mt-1 w-full"
            type="text" :value="old('name', $item->name ?? '')"
            required placeholder="Nome do titular" maxlength="255" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="expiration_date" :value="__('Data de Validade')" />
        <x-text-input name="expiration_date" id="expiration_date" class="block mt-1 w-full"
            type="date" :value="old('expiration_date', isset($item) ? $item->expiration_date->format('Y-m-d') : '')"
            required />
        <x-input-error :messages="$errors->get('expiration_date')" class="mt-2" />
    </div>

</div>
