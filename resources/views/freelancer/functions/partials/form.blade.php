@php
    $function = $function ?? null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados da Função</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $function?->name) }}" required
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Preço (R$ por 15 min) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $function?->price) }}" required
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Este valor é cobrado por bloco de 15 minutos, não por hora.</p>
            @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Descrição</label>
            <textarea name="description" rows="3"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ old('description', $function?->description) }}</textarea>
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
