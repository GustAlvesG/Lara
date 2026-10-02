@php
    $freelancer = $freelancer ?? null;
@endphp

<div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
    <div class="p-6 border-b border-line bg-subtle">
        <h2 class="text-lg font-bold text-ink">Dados do Freelancer</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        @include('freelancer.freelancers.partials.photo')

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Nome <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $freelancer?->name) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">CPF <span class="text-danger">*</span></label>
            <input type="text" name="cpf" maxlength="11" value="{{ old('cpf', $freelancer?->cpf) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('cpf')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Chave PIX</label>
            <input type="text" name="pix_key" value="{{ old('pix_key', $freelancer?->pix_key) }}"
                placeholder="Se vazio, será igual ao CPF"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            <p class="mt-1 text-xs text-ink-3">Se não preenchida, assume o mesmo valor do CPF.</p>
            @error('pix_key')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">RG <span class="text-danger">*</span></label>
            <input type="text" name="rg" value="{{ old('rg', $freelancer?->rg) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('rg')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">E-mail</label>
            <input type="email" name="email" value="{{ old('email', $freelancer?->email) }}"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Telefone <span class="text-danger">*</span></label>
            <input type="text" name="telephone" value="{{ old('telephone', $freelancer?->telephone) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('telephone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Nacionalidade <span class="text-danger">*</span></label>
            <input type="text" name="nacionality" value="{{ old('nacionality', $freelancer?->nacionality) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('nacionality')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Estado Civil <span class="text-danger">*</span></label>
            <input type="text" name="civil_status" value="{{ old('civil_status', $freelancer?->civil_status) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('civil_status')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Endereço <span class="text-danger">*</span></label>
            <input type="text" name="address" value="{{ old('address', $freelancer?->address) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('address')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
