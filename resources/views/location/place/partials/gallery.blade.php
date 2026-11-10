{{--
    Galeria de fotos do espaço social — o carrossel do site de locação.
    Fica FORA do formulário do espaço (form não aninha): cada ação aqui tem o
    próprio <form>. A capa continua sendo a imagem do cadastro acima; a
    galeria é o que vem além dela.
--}}
@php
    $photos = $photos ?? collect();
    $max = \App\Models\Social\PlacePhoto::MAX_PER_PLACE;
    $maxMb = \App\Services\Social\PlacePhotoService::MAX_UPLOAD_KB / 1024;
@endphp

<section id="galeria" class="rounded-2xl border border-grena/40 overflow-hidden bg-surface">
    <div class="px-5 py-3 bg-grena-tint border-b border-grena/40 flex items-center justify-between gap-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-grena-ink">Galeria de fotos (site)</p>
        <span class="text-xs font-semibold text-grena-ink">{{ $photos->count() }} de {{ $max }}</span>
    </div>

    <div class="px-5 py-5 space-y-5">
        <p class="text-sm text-ink-2">
            Fotos que o sócio vê no carrossel do espaço, no site de locação, na ordem abaixo. A capa continua sendo a imagem do cadastro;
            use <strong>Usar como capa</strong> para trocá-la por uma destas. Fotos escondidas não aparecem no site.
        </p>

        @include('partials.alerts')
        @error('photos')
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">{{ $message }}</div>
        @enderror
        @error('photos.*')
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">{{ $message }}</div>
        @enderror

        @if ($photos->count() < $max)
            <form method="POST" action="{{ route('place-group.photos.store', $place->id) }}" enctype="multipart/form-data"
                  class="flex flex-col gap-3 sm:flex-row sm:items-end rounded-xl border border-dashed border-line-strong p-4">
                @csrf
                <div class="flex-1">
                    <label for="gallery-photos" class="block text-sm font-semibold text-ink mb-1">Adicionar fotos</label>
                    <input type="file" id="gallery-photos" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-ink file:mr-3 file:rounded-full file:border-0 file:bg-subtle file:px-4 file:py-2 file:font-bold file:text-ink">
                    <p class="mt-1 text-xs text-ink-3">JPG, PNG ou WEBP, até {{ $maxMb }} MB cada; várias de uma vez. O Lara reduz e otimiza cada foto para o site.</p>
                </div>
                <x-primary-button>Enviar</x-primary-button>
            </form>
        @endif

        @if ($photos->isEmpty())
            <x-empty-state icon="image">Nenhuma foto na galeria. O site mostra só a capa.</x-empty-state>
        @else
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($photos as $photo)
                    <li class="rounded-xl border border-line overflow-hidden {{ $photo->active ? '' : 'opacity-60' }}" data-photo="{{ $photo->id }}">
                        <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="block aspect-[4/3] bg-subtle">
                            <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->caption ?? 'Foto ' . $loop->iteration }}" loading="lazy" class="h-full w-full object-cover">
                        </a>
                        <div class="p-3 space-y-3">
                            <div class="flex items-center justify-between text-xs text-ink-3">
                                <span class="font-bold text-ink">#{{ $loop->iteration }}</span>
                                <span>{{ $photo->width }}×{{ $photo->height }}</span>
                                @unless ($photo->active)<span class="rounded-full bg-warn-soft px-2 py-0.5 font-bold text-warn">Escondida</span>@endunless
                            </div>

                            <form method="POST" action="{{ route('place-group.photos.update', $photo->id) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="caption" maxlength="120" value="{{ $photo->caption }}" placeholder="Legenda (opcional)"
                                       aria-label="Legenda da foto {{ $loop->iteration }}"
                                       class="w-full rounded-lg border border-line-strong bg-surface px-3 py-1.5 text-sm text-ink">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="flex items-center gap-2 text-xs font-semibold text-ink">
                                        <input type="checkbox" name="active" value="1" @checked($photo->active) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                                        Mostrar no site
                                    </label>
                                    <x-secondary-button type="submit" size="sm">Salvar</x-secondary-button>
                                </div>
                            </form>

                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('place-group.photos.move', $photo->id) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <x-secondary-button type="submit" size="sm" :disabled="$loop->first" aria-label="Mover para antes">↑</x-secondary-button>
                                </form>
                                <form method="POST" action="{{ route('place-group.photos.move', $photo->id) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <x-secondary-button type="submit" size="sm" :disabled="$loop->last" aria-label="Mover para depois">↓</x-secondary-button>
                                </form>
                                <form method="POST" action="{{ route('place-group.photos.cover', $photo->id) }}">
                                    @csrf
                                    <x-secondary-button type="submit" size="sm">Usar como capa</x-secondary-button>
                                </form>
                                <form method="POST" action="{{ route('place-group.photos.destroy', $photo->id) }}" class="ml-auto"
                                      onsubmit="return confirm('Excluir esta foto da galeria?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button size="sm" aria-label="Excluir foto {{ $loop->iteration }}"><x-icon name="trash" class="h-3.5 w-3.5" /></x-danger-button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
