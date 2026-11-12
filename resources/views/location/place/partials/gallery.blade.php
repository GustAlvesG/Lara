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
            {{--
                Envio: o script abaixo reduz cada foto no navegador (lado maior
                1920 px, JPEG) e manda UMA por requisição. Várias fotos de
                celular num POST só estouravam o post_max_size do PHP ("POST
                too large"), e uma foto sozinha o upload_max_filesize ("falha no
                upload do campo photos.0"). Sem JavaScript, o formulário comum
                ainda funciona, dentro desses limites.
            --}}
            <form method="POST" action="{{ route('place-group.photos.store', $place->id) }}" enctype="multipart/form-data"
                  data-gallery-upload data-return="{{ route('place-group.editPlace', $place->id) }}" data-free="{{ $max - $photos->count() }}"
                  class="space-y-3 rounded-xl border border-dashed border-line-strong p-4">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="gallery-photos" class="block text-sm font-semibold text-ink mb-1">Adicionar fotos</label>
                        <input type="file" id="gallery-photos" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-ink file:mr-3 file:rounded-full file:border-0 file:bg-subtle file:px-4 file:py-2 file:font-bold file:text-ink">
                        <p class="mt-1 text-xs text-ink-3">JPG, PNG ou WEBP; várias de uma vez. O navegador reduz cada foto antes de enviar, e elas sobem uma por vez.</p>
                    </div>
                    <x-primary-button data-gallery-submit>Enviar</x-primary-button>
                </div>
                <p data-gallery-progress class="hidden text-sm font-semibold text-ink" role="status" aria-live="polite"></p>
                <div data-gallery-errors class="hidden rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert"></div>
            </form>

            <script>
            (function () {
                var form = document.currentScript.previousElementSibling;
                if (!form || !window.fetch || !window.FormData || !document.createElement('canvas').toBlob) {
                    return; // navegador antigo: fica o envio comum do formulário
                }

                var MAX_SIDE = 1920;
                var QUALITY = 0.9;
                // Já pequena e em JPEG: vai como está (evita recomprimir).
                var KEEP_UNDER = 1.5 * 1024 * 1024;

                var input = form.querySelector('input[type=file]');
                var button = form.querySelector('[data-gallery-submit]');
                var progress = form.querySelector('[data-gallery-progress]');
                var errorBox = form.querySelector('[data-gallery-errors]');
                var token = form.querySelector('input[name=_token]').value;

                function loadImage(file) {
                    return new Promise(function (resolve, reject) {
                        var url = URL.createObjectURL(file);
                        var img = new Image();
                        img.onload = function () { resolve({ img: img, url: url }); };
                        img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
                        img.src = url;
                    });
                }

                // Reduz e regrava em JPEG. O navegador já aplica a orientação
                // da câmera (EXIF) ao desenhar, então a foto sai "em pé".
                function shrink(file) {
                    return loadImage(file).then(function (loaded) {
                        var img = loaded.img;
                        var w = img.naturalWidth, h = img.naturalHeight;
                        var scale = Math.min(1, MAX_SIDE / Math.max(w, h));

                        if (scale === 1 && file.type === 'image/jpeg' && file.size <= KEEP_UNDER) {
                            URL.revokeObjectURL(loaded.url);
                            return file;
                        }

                        var canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(w * scale));
                        canvas.height = Math.max(1, Math.round(h * scale));
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#fff'; // PNG transparente: fundo branco, como no servidor
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        URL.revokeObjectURL(loaded.url);

                        return new Promise(function (resolve) {
                            canvas.toBlob(function (blob) {
                                var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                                resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : file);
                            }, 'image/jpeg', QUALITY);
                        });
                    }).catch(function () {
                        return file; // o navegador não abriu: o servidor decide (e explica)
                    });
                }

                function send(file) {
                    var data = new FormData();
                    data.append('_token', token);
                    data.append('photos[]', file, file.name);

                    return fetch(form.action, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (body) {
                            if (response.ok) {
                                return null;
                            }
                            var errors = body.errors ? Object.values(body.errors).flat() : [];
                            if (errors.length) {
                                return errors[0];
                            }
                            if (response.status === 413) {
                                return 'A foto passou do limite de envio do servidor.';
                            }
                            if (response.status === 419) {
                                return 'A sessão expirou. Recarregue a página e envie de novo.';
                            }
                            return body.message || ('Falha no envio (código ' + response.status + ').');
                        });
                    }).catch(function () {
                        return 'Sem conexão com o Lara. Tente de novo.';
                    });
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    var files = Array.prototype.slice.call(input.files || []);
                    if (!files.length) {
                        return;
                    }

                    var free = parseInt(form.dataset.free, 10);
                    var failures = [];
                    if (files.length > free) {
                        failures.push('A galeria só tem lugar para mais ' + free + (free === 1 ? ' foto' : ' fotos') + '; as ' + (files.length - free) + ' últimas não foram enviadas.');
                        files = files.slice(0, free);
                    }

                    var sent = 0;
                    button.disabled = true;
                    input.disabled = true;
                    errorBox.classList.add('hidden');
                    progress.classList.remove('hidden');

                    files.reduce(function (chain, file, index) {
                        return chain.then(function () {
                            progress.textContent = 'Enviando ' + (index + 1) + ' de ' + files.length + '…';
                            return shrink(file).then(send).then(function (error) {
                                if (error) {
                                    failures.push(file.name + ': ' + error);
                                } else {
                                    sent++;
                                }
                            });
                        });
                    }, Promise.resolve()).then(function () {
                        var message = sent === 1 ? 'Foto adicionada à galeria.' : sent + ' fotos adicionadas à galeria.';

                        if (!failures.length) {
                            window.location.href = form.dataset.return + '?success=' + encodeURIComponent(message) + '#galeria';
                            return;
                        }

                        // Com falha, a tela fica para a pessoa ler o motivo; as
                        // que subiram já estão gravadas.
                        progress.textContent = sent ? message + ' Recarregue a página para vê-las.' : '';
                        progress.classList.toggle('hidden', !sent);
                        errorBox.innerHTML = '';
                        failures.forEach(function (text) {
                            var p = document.createElement('p');
                            p.textContent = text;
                            errorBox.appendChild(p);
                        });
                        errorBox.classList.remove('hidden');
                        button.disabled = false;
                        input.disabled = false;
                        input.value = '';
                        form.dataset.free = String(Math.max(0, free - sent));
                    });
                });
            })();
            </script>
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
