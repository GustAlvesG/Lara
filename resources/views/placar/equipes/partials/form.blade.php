@php
    $equipe = $equipe ?? null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados da Equipe</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $equipe?->nome) }}" required
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            @error('nome')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome curto</label>
            <input type="text" name="nome_curto" value="{{ old('nome_curto', $equipe?->nome_curto) }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Usado no placar quando o nome completo não cabe.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Cidade</label>
            <input type="text" name="cidade" value="{{ old('cidade', $equipe?->cidade) }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
        </div>

        @if($equipe)
        <div class="md:col-span-2">
            <label class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $equipe->ativo))
                    class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Ativa</span>
            </label>
        </div>
        @endif
    </div>
</div>
