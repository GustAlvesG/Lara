{{-- Imagem por arquivo ou por link web (imgur, postimg…), com prévia.
     Parâmetros: name (campo; o link vai em {name}_url), label, current (valor
     gravado: nome de arquivo ou URL), ratio (classe de proporção da prévia).
     Quem grava é App\Support\ImageSource::resolve: arquivo enviado, senão
     link, senão mantém o atual. --}}
@php
    $current = $current ?? null;
    $currentUrl = \App\Support\ImageSource::url($current);
    $currentIsLink = \App\Support\ImageSource::isRemote($current);
    $oldLink = old($name . '_url');
    $mode = $oldLink ? 'url' : 'file';
    $ratio = $ratio ?? 'aspect-video';
    $tab = 'cursor-pointer rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide transition has-[:checked]:border-grena has-[:checked]:bg-grena-tint has-[:checked]:text-grena-ink border-line-strong text-ink-2';
@endphp
<div data-image-source class="space-y-3">
    <p class="text-xs font-bold uppercase tracking-tight text-ink-2">{{ $label ?? 'Imagem' }}</p>

    <div class="{{ $ratio }} relative overflow-hidden rounded-xl border border-line bg-subtle">
        <img data-image-preview src="{{ $currentUrl }}" alt="" class="{{ $currentUrl ? '' : 'hidden' }} h-full w-full object-cover">
        <p data-image-empty class="{{ $currentUrl ? 'hidden' : '' }} absolute inset-0 flex items-center justify-center text-xs text-ink-3">Sem imagem</p>
    </div>
    <p data-image-error class="hidden text-xs font-bold text-danger">Não foi possível carregar a imagem desse link. Confira se é o link direto da imagem.</p>
    @if ($current)
        <p class="text-[11px] text-ink-3">
            Atual: {{ $currentIsLink ? 'link externo' : 'arquivo enviado' }}
            @if ($currentIsLink) — <a href="{{ $currentUrl }}" target="_blank" rel="noopener" class="break-all font-bold text-grena-ink hover:underline">{{ \Illuminate\Support\Str::limit($current, 60) }}</a>@endif
            . Sem arquivo nem link novo, ela fica.
        </p>
    @endif

    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Origem da imagem">
        <label class="{{ $tab }}"><input type="radio" class="sr-only" name="_{{ $name }}_source" value="file" data-image-mode @checked($mode === 'file')> Enviar arquivo</label>
        <label class="{{ $tab }}"><input type="radio" class="sr-only" name="_{{ $name }}_source" value="url" data-image-mode @checked($mode === 'url')> Usar link</label>
    </div>

    <div data-image-pane="file" class="{{ $mode === 'file' ? '' : 'hidden' }}">
        <input type="file" name="{{ $name }}" id="{{ $name }}" accept="image/jpeg,image/png,image/webp,image/gif" data-image-file
               class="block w-full text-xs text-ink-2 file:mr-3 file:rounded-full file:border-0 file:bg-grena-tint file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-grena-ink">
        <p class="mt-1 text-[11px] text-ink-3">JPG, PNG, WEBP ou GIF.</p>
    </div>

    <div data-image-pane="url" class="{{ $mode === 'url' ? '' : 'hidden' }}">
        <x-input-label :for="$name . '_url'" value="Link da imagem" class="sr-only" />
        <x-text-input type="url" :id="$name . '_url'" :name="$name . '_url'" :value="$oldLink" data-image-url maxlength="255"
                      placeholder="https://i.imgur.com/abc123.jpg" />
        <p class="mt-1 text-[11px] text-ink-3">Link direto da imagem (termina em .jpg, .png…). Página do imgur ou do postimg também serve: o Lara acha a imagem ao salvar.</p>
    </div>

    @error($name)<p class="text-xs font-bold text-danger">{{ $message }}</p>@enderror
    @error($name . '_url')<p class="text-xs font-bold text-danger">{{ $message }}</p>@enderror
</div>

@once
    <script>
        // Prévia do arquivo escolhido ou do link colado; trocar de aba limpa a
        // outra, para não mandar arquivo e link juntos (o arquivo venceria).
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-image-source]').forEach(box => {
                const img = box.querySelector('[data-image-preview]');
                const empty = box.querySelector('[data-image-empty]');
                const error = box.querySelector('[data-image-error]');
                const file = box.querySelector('[data-image-file]');
                const url = box.querySelector('[data-image-url]');
                const original = img.getAttribute('src') || '';

                const show = src => {
                    error.classList.add('hidden');
                    img.classList.toggle('hidden', !src);
                    empty.classList.toggle('hidden', !!src);
                    if (src) img.src = src; else img.removeAttribute('src');
                };
                // Página do imgur vira o link direto também na prévia; a do
                // postimg só o servidor resolve (lê a página), então a prévia espera.
                const direct = value => {
                    const imgur = value.match(/^https:\/\/(?:www\.|m\.)?imgur\.com\/([A-Za-z0-9]{5,10})\/?$/);
                    if (imgur) return `https://i.imgur.com/${imgur[1]}.jpg`;
                    if (/^https:\/\/(www\.)?(postimg\.cc|postimages\.org)\//.test(value)) return null;
                    return value;
                };

                img.addEventListener('error', () => { if (img.getAttribute('src')) { error.classList.remove('hidden'); img.classList.add('hidden'); empty.classList.remove('hidden'); } });

                box.querySelectorAll('[data-image-mode]').forEach(radio => radio.addEventListener('change', () => {
                    box.querySelectorAll('[data-image-pane]').forEach(p => p.classList.toggle('hidden', p.dataset.imagePane !== radio.value));
                    if (radio.value === 'file') url.value = ''; else file.value = '';
                    show(original);
                }));

                file.addEventListener('change', () => {
                    const chosen = file.files[0];
                    if (!chosen) { show(original); return; }
                    const reader = new FileReader();
                    reader.onload = e => show(e.target.result);
                    reader.readAsDataURL(chosen);
                });

                url.addEventListener('input', () => {
                    const value = url.value.trim();
                    if (!value) { show(original); return; }
                    const src = direct(value);
                    if (src === null) { show(''); empty.innerText = 'A imagem do postimg aparece depois de salvar.'; return; }
                    empty.innerText = 'Sem imagem';
                    show(src);
                });
            });
        });
    </script>
@endonce
