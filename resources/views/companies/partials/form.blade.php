<!-- Nome da Empresa -->
<div class="md:col-span-2">
    <label for="name" class="block text-sm font-bold text-ink mb-1">Nome da Empresa</label>
    <input type="text" id="name" name="name" required placeholder="Ex: TecnoLogistics S.A."
        value="{{ old('name', $model->name ?? '') }}"
        class="w-full px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card bg-surface text-ink">
</div>

<!-- Email -->
<div>
    <label for="email" class="block text-sm font-bold text-ink mb-1">E-mail Corporativo</label>
    <input type="email" id="email" name="email" required placeholder="contacto@empresa.com"
        value="{{ old('email', $model->email ?? '') }}"
        class="w-full px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card bg-surface text-ink">
</div>

<!-- Telefone -->
<div>
    <label for="telephone" class="block text-sm font-bold text-ink mb-1">Telefone</label>
    <input type="text" id="telephone" name="telephone" placeholder="+351 912 345 678"
        value="{{ old('telephone', $model->telephone ?? '') }}"
        class="w-full px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card bg-surface text-ink">
</div>

<!-- Endereço -->
<div class="md:col-span-2">
    <label for="address" class="block text-sm font-bold text-ink mb-1">Endereço Completo</label>
    <input type="text" id="address" name="address" placeholder="Rua, Número, Código Postal, Cidade"
        value="{{ old('address', $model->address ?? '') }}"
        class="w-full px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card bg-surface text-ink">
</div>

<!-- Descrição -->
<div class="md:col-span-2">
    <label for="description" class="block text-sm font-bold text-ink mb-1">Descrição / Sobre a Empresa</label>
    <textarea id="description" name="description" rows="4" placeholder="Breve resumo sobre a atividade da empresa..."
            class="w-full px-4 py-3 border border-line rounded-xl focus:ring-2 focus:ring-grena-tint focus:border-grena outline-none transition shadow-card resize-none bg-surface text-ink">{{ old('description', $model->description ?? '') }}</textarea>
</div>
