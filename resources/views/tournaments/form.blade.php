{{-- Cadastro e edição do torneio. As categorias ligam-se depois, na tela do
     torneio; com categoria ligada, o grupo de espaços não muda. --}}
@php
    $item = $tournament;
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $area = 'mt-1 w-full rounded-xl border border-line-strong bg-surface px-3 py-2 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$item ? 'Editar ' . $item->title : 'Novo torneio'" :back="$item ? route('tournaments.show', $item) : route('tournaments.index')">
            O torneio nasce em rascunho e só aparece no site depois de publicado. Inscrição e pagamento só dentro do período de inscrição.
        </x-page-title>

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($groups->isEmpty())
            <x-empty-state icon="trophy">Nenhum grupo de espaços esportivo cadastrado. Cadastre em Reservas › Locais e Grupos.</x-empty-state>
        @else
            <form method="POST" action="{{ $item ? route('tournaments.update', $item) : route('tournaments.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
                @csrf
                @if ($item) @method('PUT') @endif

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-2">
                    <div class="sm:col-span-2"><x-input-label for="title" value="Nome" /><x-text-input id="title" name="title" class="mt-1" :value="old('title', $item->title ?? '')" required maxlength="255" placeholder="Ex.: Copa de Verão de Beach Tennis" /></div>
                    <div>
                        <x-input-label for="place_group_id" value="Grupo de espaços (modalidade)" />
                        <select id="place_group_id" name="place_group_id" class="{{ $select }}" required>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected((int) old('place_group_id', $item->place_group_id ?? 0) === $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-3">As categorias vêm do catálogo deste grupo.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="description" value="Descrição" />
                        <textarea id="description" name="description" rows="3" maxlength="5000" class="{{ $area }}">{{ old('description', $item->description ?? '') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="rules" value="Regulamento" />
                        <textarea id="rules" name="rules" rows="8" class="{{ $area }}">{{ old('rules', $item->rules ?? '') }}</textarea>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Datas</h2>
                    <div><x-input-label for="registration_starts_at" value="Inscrições abrem" /><x-text-input type="datetime-local" id="registration_starts_at" name="registration_starts_at" class="mt-1" :value="old('registration_starts_at', $dt($item?->registration_starts_at))" required /></div>
                    <div><x-input-label for="registration_ends_at" value="Inscrições fecham" /><x-text-input type="datetime-local" id="registration_ends_at" name="registration_ends_at" class="mt-1" :value="old('registration_ends_at', $dt($item?->registration_ends_at))" required /></div>
                    <div><x-input-label for="starts_on" value="Primeiro dia" /><x-text-input type="date" id="starts_on" name="starts_on" class="mt-1" :value="old('starts_on', $item?->starts_on?->toDateString())" required /></div>
                    <div><x-input-label for="ends_on" value="Último dia" /><x-text-input type="date" id="ends_on" name="ends_on" class="mt-1" :value="old('ends_on', $item?->ends_on?->toDateString())" required /></div>
                    <p class="text-xs text-ink-3 sm:col-span-4">A idade das categorias é conferida no primeiro dia do torneio.</p>
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
                    <x-secondary-button-a href="{{ $item ? route('tournaments.show', $item) : route('tournaments.index') }}">Cancelar</x-secondary-button-a>
                    <x-primary-button>{{ $item ? 'Salvar' : 'Criar torneio' }}</x-primary-button>
                </div>
            </form>
        @endif
    </x-page>
</x-app-layout>
