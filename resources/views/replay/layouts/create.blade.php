<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('RePET — Novo Layout') }}
        </h2>
    </x-slot>

<div class="py-12 bg-subtle min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('replay.layouts.store') }}" method="POST"
              class="bg-surface rounded-2xl shadow-card border border-line p-8 space-y-6"
              x-data="{ ownerType: 'group' }">
            @csrf

            <div>
                <label class="block text-sm font-bold text-ink-2 mb-2">Nome do layout</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                       placeholder="Ex.: Patrocínio 2026 — Tênis"
                       class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-2 mb-2">Aplica-se a</label>
                <div class="flex gap-4 mb-3">
                    <label class="flex items-center gap-2 text-sm text-ink-2">
                        <input type="radio" name="owner_type" value="group" x-model="ownerType" checked> Esporte (vale para todas as quadras)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink-2">
                        <input type="radio" name="owner_type" value="place" x-model="ownerType"> Quadra específica
                    </label>
                </div>

                {{--
                    Dois selects, e não um com as opções escondidas: `x-show`
                    em <option> não funciona igual em todo navegador. O que
                    está fora de cena fica `disabled`, e select desabilitado
                    não é enviado — então chega sempre um `owner_id` só.
                --}}
                <select name="owner_id" x-show="ownerType === 'group'" :disabled="ownerType !== 'group'"
                        class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>

                <select name="owner_id" x-show="ownerType === 'place'" :disabled="ownerType !== 'place'" x-cloak
                        class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    @foreach($places as $place)
                        <option value="{{ $place->id }}">
                            {{ $place->group?->name ? $place->group->name . ' — ' : '' }}{{ $place->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-ink-2">
                    O layout da quadra sempre vence o do esporte — mesma regra da configuração de vídeo.
                </p>
            </div>

            <div>
                <label class="block text-sm font-bold text-ink-2 mb-2">Orientação</label>
                <select name="orientation" required
                        class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    @foreach($orientations as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-ink-2">
                    Não dá para trocar depois: as posições são desenhadas nesta tela. Para o outro formato, crie um segundo layout.
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('replay.layouts.index') }}"
                   class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-lg hover:bg-grena-hover transition">
                    Criar e enviar logomarcas
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
