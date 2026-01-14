<input type="hidden" name="company_id" value="{{ $companyId }}">

<!-- Nome Completo -->
<div class="md:col-span-2">
    <label for="name" class="block text-sm font-bold text-gray-700 mb-1">Nome Completo</label>
    <input type="text" id="name" name="name" required placeholder="Ex: João Silva"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- Email -->
<div>
    <label for="email" class="block text-sm font-bold text-gray-700 mb-1">E-mail</label>
    <input type="email" id="email" name="email" required placeholder="joao.silva@empresa.com"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- CPF / Documento -->
<div>
    <label for="document" class="block text-sm font-bold text-gray-700 mb-1">CPF / Documento</label>
    <input type="text" id="document" name="document" placeholder="000.000.000-00"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- Cargo -->
<div>
    <label for="role" class="block text-sm font-bold text-gray-700 mb-1">Cargo / Função</label>
    <select id="role" name="role" required
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white">
        <option value="">Selecione o Cargo</option>
        <option value="motorista">Motorista</option>
        <option value="logistica">Logística</option>
        <option value="operador">Operador</option>
        <option value="administrativo">Administrativo</option>
    </select>
</div>

<!-- Telefone -->
<div>
    <label for="phone" class="block text-sm font-bold text-gray-700 mb-1">Telefone</label>
    <input type="text" id="phone" name="phone" placeholder="(24) 99999-9999"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>