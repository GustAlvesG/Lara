@php
    use App\Models\FunctionFreelancer;

    $function = $function ?? null;
    $currentType = old('type', $function?->type ?? FunctionFreelancer::TYPE_FREELANCER);
    $rates = $function ? $function->ratesByHour() : collect();
    // Função já usada não troca de modalidade: mudaria a conta de lançamentos
    // que já existem. O caminho é cadastrar outra função.
    $lockedType = $function && !$function->canChangeType();
@endphp

<div x-data="{ type: '{{ $currentType }}' }" class="space-y-8">

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
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Modalidade <span class="text-red-500">*</span></label>
                <select name="type" x-model="type" @if($lockedType) disabled @endif
                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60">
                    @foreach(FunctionFreelancer::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($lockedType)
                    <input type="hidden" name="type" value="{{ $function->type }}">
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Esta função já tem lançamentos: a modalidade não pode mais ser trocada.</p>
                @else
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Freelancer é pago por bloco de 15 min; cachê, por faixa de horas.</p>
                @endif
                @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="type === '{{ FunctionFreelancer::TYPE_FREELANCER }}'" x-cloak>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Preço (R$ por 15 min) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $function?->price) }}"
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

    {{-- Faixas do cachê: uma linha por hora cheia, de 2h a 11h. Não é uma
         fórmula porque o cachê não é proporcional — cada faixa tem seu valor
         negociado, e 5h pode não valer 2,5 × a faixa de 2h. --}}
    <div x-show="type === '{{ FunctionFreelancer::TYPE_CACHE }}'" x-cloak
         class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Tabela de cachê (R$ por faixa)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Valor fixo por faixa de horas, sem proporção. Turnos de até 2h pagam a faixa de 2h; de
                11h em diante, a faixa de 11h. O arredondamento soma 15 minutos e considera a hora do
                resultado — <strong>3h45 paga 4h</strong>, 3h44 paga 3h.
            </p>
        </div>

        <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach(FunctionFreelancer::cacheHourRange() as $hours)
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                        {{ $hours }}h
                        @if($hours === FunctionFreelancer::CACHE_MAX_HOURS)<span class="font-normal text-xs text-gray-400">ou mais</span>@endif
                    </label>
                    <input type="number" step="0.01" min="0" name="cache_rates[{{ $hours }}]"
                        value="{{ old('cache_rates.' . $hours, $rates[$hours] ?? null) }}"
                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                    @error('cache_rates.' . $hours)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </div>
</div>
