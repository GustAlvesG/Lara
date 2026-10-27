{{-- Cadastro e edição do evento. Tipos de ingresso e lotes entram depois, na
     tela do evento. --}}
@php
    $item = $event;
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
    $area = 'mt-1 w-full rounded-xl border border-line-strong bg-surface px-3 py-2 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$item ? 'Editar ' . $item->title : 'Novo evento'" :back="$item ? route('tickets.show', $item) : route('tickets.index')">
            O evento nasce em rascunho e só aparece no site depois de publicado. O site vende só dentro do período de vendas.
        </x-page-title>

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ $item ? route('tickets.update', $item) : route('tickets.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
            @csrf
            @if ($item) @method('PUT') @endif

            <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2">
                <div class="sm:col-span-2"><x-input-label for="title" value="Nome" /><x-text-input id="title" name="title" class="mt-1" :value="old('title', $item->title ?? '')" required maxlength="255" placeholder="Ex.: Baile de Carnaval 2027" /></div>
                <div class="sm:col-span-2"><x-input-label for="venue" value="Local" /><x-text-input id="venue" name="venue" class="mt-1" :value="old('venue', $item->venue ?? '')" maxlength="255" placeholder="Ex.: Salão Nobre" /></div>
                <div class="sm:col-span-2">
                    <x-input-label for="description" value="Descrição" />
                    <textarea id="description" name="description" rows="3" maxlength="5000" class="{{ $area }}">{{ old('description', $item->description ?? '') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="rules" value="Regras e informações (aparecem no site)" />
                    <textarea id="rules" name="rules" rows="6" class="{{ $area }}">{{ old('rules', $item->rules ?? '') }}</textarea>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
                <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Datas</h2>
                <div><x-input-label for="starts_at" value="Início do evento" /><x-text-input type="datetime-local" id="starts_at" name="starts_at" class="mt-1" :value="old('starts_at', $dt($item?->starts_at))" required /></div>
                <div><x-input-label for="ends_at" value="Fim do evento" /><x-text-input type="datetime-local" id="ends_at" name="ends_at" class="mt-1" :value="old('ends_at', $dt($item?->ends_at))" required /></div>
                <div><x-input-label for="sales_starts_at" value="Vendas abrem" /><x-text-input type="datetime-local" id="sales_starts_at" name="sales_starts_at" class="mt-1" :value="old('sales_starts_at', $dt($item?->sales_starts_at))" required /></div>
                <div><x-input-label for="sales_ends_at" value="Vendas fecham" /><x-text-input type="datetime-local" id="sales_ends_at" name="sales_ends_at" class="mt-1" :value="old('sales_ends_at', $dt($item?->sales_ends_at))" required /></div>
                <p class="text-xs text-ink-3 sm:col-span-4">Cada lote pode ter a própria janela, dentro desta. O site para de vender no fim do evento, mesmo que o fechamento das vendas esteja marcado para depois.</p>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2">
                <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-2">Limites</h2>
                <div>
                    <x-input-label for="capacity" value="Lotação do evento" />
                    <x-text-input type="number" min="1" id="capacity" name="capacity" class="mt-1" :value="old('capacity', $item->capacity ?? '')" placeholder="Sem limite" />
                    <p class="mt-1 text-xs text-ink-3">Soma de todos os tipos. Vazio = só as quantidades dos lotes limitam.</p>
                </div>
                <div>
                    <x-input-label for="max_per_order" value="Ingressos por pedido (não sócio)" />
                    <x-text-input type="number" min="1" max="50" id="max_per_order" name="max_per_order" class="mt-1" :value="old('max_per_order', $item->max_per_order ?? 10)" required />
                    <p class="mt-1 text-xs text-ink-3">O sócio compra para as pessoas do título dele (titular e dependentes), um ingresso para cada.</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2">
                <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-2">Imagem</h2>
                <div><x-input-label for="image" value="Arquivo" /><input type="file" id="image" name="image" accept="image/*" class="mt-1 block w-full text-sm text-ink-2"></div>
                <div><x-input-label for="image_url" value="Ou link da imagem" /><x-text-input id="image_url" name="image_url" class="mt-1" :value="old('image_url')" placeholder="https://i.imgur.com/…" /></div>
                @if ($item?->image)
                    <img src="{{ \App\Support\ImageSource::url($item->image) }}" alt="" class="max-h-40 rounded-xl sm:col-span-2">
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <x-secondary-button-a href="{{ $item ? route('tickets.show', $item) : route('tickets.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button>{{ $item ? 'Salvar' : 'Criar evento' }}</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
