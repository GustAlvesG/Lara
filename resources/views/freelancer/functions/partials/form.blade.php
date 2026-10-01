@php
    $function = $function ?? null;
@endphp

<div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
    <div class="p-6 border-b border-line bg-subtle">
        <h2 class="text-lg font-bold text-ink">Dados da Função</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-ink mb-1">Nome <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $function?->name) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Preço (R$ por 15 min) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $function?->price) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
            <p class="mt-1 text-xs text-ink-3">Este valor é cobrado por bloco de 15 minutos, não por hora.</p>
            @error('price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        {{-- Quem habilita a comissão de venda é esta caixa, e não o nome da
             função no código: quando outra função passar a receber comissão,
             basta marcá-la aqui. --}}
        <div class="md:col-span-2">
            <label class="flex items-start gap-3 p-4 rounded-xl border border-line cursor-pointer hover:bg-subtle transition">
                <input type="hidden" name="allows_sales_commission" value="0">
                <input type="checkbox" name="allows_sales_commission" value="1"
                       @checked(old('allows_sales_commission', $function?->allows_sales_commission))
                       class="mt-0.5 w-5 h-5 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                <span>
                    <span class="block text-sm font-bold text-ink">Permite comissão de venda</span>
                    <span class="block text-xs text-ink-3 mt-0.5">
                        Contratos desta função podem receber, no tablet, o aditivo de comissão sobre as vendas do
                        turno — assinado ao final do expediente e pago <b>além</b> do valor do contrato.
                    </span>
                </span>
            </label>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Descrição</label>
            <textarea name="description" rows="3"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">{{ old('description', $function?->description) }}</textarea>
            @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
