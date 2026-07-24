<input type="hidden" name="place_group_id" value="{{ $place_group->id ?? $item->group->id ?? '' }}">

<div class="space-y-5">

    {{-- ─── Informações Básicas ──────────────────────────────────────── --}}
    <div class="rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-700">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                Informações Básicas
            </p>
        </div>
        <div class="px-5 py-5 bg-white dark:bg-gray-800 grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Nome --}}
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Nome do Local
                </label>
                <x-text-input name="name" id="name" class="w-full" value="{{ $item->name ?? '' }}" required/>
            </div>

            {{-- Status --}}
            <div>
                <label for="status_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Status
                </label>
                <select name="status_id" id="status_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm
                           focus:border-indigo-500 focus:ring-indigo-500
                           bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm">
                    <option value="1" @if(isset($item) && $item->status_id == 1) selected @endif>Ativo</option>
                    <option value="2" @if(isset($item) && $item->status_id == 2) selected @endif>Inativo</option>
                </select>
            </div>

            {{-- Preço --}}
            <div>
                <label for="price" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Preço
                </label>
                <x-text-input type="number" step="0.01" min="0.00" name="price" id="price" class="w-full"
                    value="{{ $item->price ?? '' }}" required/>
            </div>

        </div>
    </div>

    {{-- ─── Home Assistant ───────────────────────────────────────────── --}}
    @php
        $canManageHA = auth()->user()->can('manage home assistant');
    @endphp
    <div class="rounded-2xl border overflow-hidden
        {{ $canManageHA ? 'border-indigo-200 dark:border-indigo-800' : 'border-gray-100 dark:border-gray-700' }}">

        <div class="px-5 py-3 border-b flex items-center gap-2
            {{ $canManageHA
                ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-100 dark:border-indigo-800'
                : 'bg-gray-50 dark:bg-gray-900/50 border-gray-100 dark:border-gray-700' }}">
            <svg class="w-4 h-4 {{ $canManageHA ? 'text-indigo-500 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <p class="text-[10px] font-black uppercase tracking-widest
                {{ $canManageHA ? 'text-indigo-500 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500' }}">
                Home Assistant
            </p>
            @if (!$canManageHA)
                <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                             bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Restrito a TI
                </span>
            @endif
        </div>

        <div class="px-5 py-4 bg-white dark:bg-gray-800">
            <label for="contactor_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Switch vinculado
            </label>
            <select name="contactor_id" id="contactor_id"
                @if (!$canManageHA) disabled @endif
                class="w-full px-3 py-2 border rounded-lg shadow-sm text-sm transition
                       focus:border-indigo-500 focus:ring-indigo-500
                       {{ $canManageHA
                            ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white'
                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-gray-400 dark:text-gray-500 cursor-not-allowed' }}">
                <option value="">— Nenhum —</option>
                @foreach($contactors as $contactor)
                    <option value="{{ $contactor->id }}"
                        @if(isset($item) && $item->contactor_id == $contactor->id) selected @endif>
                        {{ $contactor->name }} ({{ $contactor->entity_id }})
                    </option>
                @endforeach
            </select>
            @if (!$canManageHA)
                <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                    Apenas o departamento de TI pode alterar este campo.
                </p>
            @endif
        </div>
    </div>

    {{-- ─── Imagem ────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-700">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                Imagem
            </p>
        </div>
        <div class="px-5 py-5 bg-white dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Imagem para exibição no site.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                <div>
                    <label for="image" style="cursor: pointer;"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500
                               rounded-xl font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm
                               hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500
                               focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Selecionar imagem
                    </label>
                    <input value="{{ $item->image ?? '' }}" type="file"
                        class="image-upload" name="image" id="image"
                        style="opacity: 0; position: absolute; z-index: -1;" />
                </div>
                <div>
                    @include('partials.imagePreview', ['id_preview' => 'image'])
                </div>
            </div>
        </div>
    </div>

</div>
